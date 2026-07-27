<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\BrandContext\Facades\BrandContext;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Tests\CpTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * The HMAC secret must survive the CP save.
 *
 * The regression: `SaveOutboundWebhookRequest::rules()` never listed
 * `auth_config_json`, and `store()`/`update()` persist `$request->validated()`.
 * The secret was therefore dropped between the browser and the database. The
 * hook ended up with `auth_type = hmac` and an EMPTY `auth_config`, so
 * HmacSignatureVerifier::sign() returned the request untouched and the
 * delivery went out UNSIGNED — while the CP displayed "HMAC signature".
 *
 * Rotation failed for exactly the same reason, and reported "Webhook updated."
 * while the receiver kept verifying against the old secret.
 *
 * These tests go through the real route + FormRequest + controller path.
 * A model-level test cannot see this bug at all.
 */
class OutboundSecretSurvivesTheRequestTest extends CpTestCase
{
    use RefreshDatabase;

    /** @return array<string,mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Signed hook',
            'handle' => 'signed-hook',
            'enabled' => true,
            'trigger_type' => 'entry.saved',
            'url' => 'https://receiver.example.test/hook',
            'method' => 'POST',
            'timeout_seconds' => 15,
            'follow_redirects' => true,
            'auth_type' => 'hmac',
            'auth_config_json' => json_encode(['secret' => 'first-secret']),
            'payload_type' => 'raw_json',
            'payload_template' => '{"x":1}',
            'queue_enabled' => false,
            'log_body_mode' => 'partial',
        ], $overrides);
    }

    public function test_storing_a_hook_persists_the_hmac_secret(): void
    {
        $response = $this->actingAs($this->superUser())
            ->post(cp_route('webhook-manager.outbound.store'), $this->payload());

        $response->assertRedirect();

        $hook = OutboundWebhook::where('handle', 'signed-hook')->firstOrFail();

        $this->assertSame('hmac', $hook->auth_type);
        $this->assertSame(
            ['secret' => 'first-secret'],
            $hook->auth_config,
            'The secret was dropped on the way to the database — the hook would go out unsigned while the UI claims HMAC.'
        );

        // And it is genuinely encrypted at rest, not just readable through
        // the accessor.
        $raw = (string) DB::table('webhook_outbounds')->where('id', $hook->id)->value('auth_config');
        $this->assertNotSame('', $raw);
        $this->assertStringNotContainsString('first-secret', $raw);
    }

    public function test_rotating_the_secret_actually_changes_the_stored_value(): void
    {
        $user = $this->superUser();

        $this->actingAs($user)
            ->post(cp_route('webhook-manager.outbound.store'), $this->payload())
            ->assertRedirect();

        $hook = OutboundWebhook::where('handle', 'signed-hook')->firstOrFail();
        $this->assertSame(['secret' => 'first-secret'], $hook->auth_config);

        $this->actingAs($user)
            ->from(cp_route('webhook-manager.outbound.edit', $hook))
            ->patch(
                cp_route('webhook-manager.outbound.update', $hook),
                $this->payload(['auth_config_json' => json_encode(['secret' => 'rotated-secret'])])
            )
            ->assertRedirect();

        $this->assertSame(
            ['secret' => 'rotated-secret'],
            $hook->fresh()->auth_config,
            'Rotation reported success but left the old secret in place.'
        );
    }

    public function test_saving_with_an_empty_auth_config_keeps_the_stored_secret(): void
    {
        $user = $this->superUser();

        $this->actingAs($user)->post(cp_route('webhook-manager.outbound.store'), $this->payload());
        $hook = OutboundWebhook::where('handle', 'signed-hook')->firstOrFail();

        // This is how the edit screen says "keep what you have": the secret is
        // never sent back to the browser, so an untouched form posts a blank.
        $this->actingAs($user)
            ->from(cp_route('webhook-manager.outbound.edit', $hook))
            ->patch(
                cp_route('webhook-manager.outbound.update', $hook),
                $this->payload(['auth_config_json' => '', 'name' => 'Renamed'])
            )
            ->assertRedirect();

        $fresh = $hook->fresh();
        $this->assertSame('Renamed', $fresh->name);
        $this->assertSame(['secret' => 'first-secret'], $fresh->auth_config);
    }

    public function test_an_auth_type_without_credentials_is_rejected_instead_of_silently_unsigned(): void
    {
        $this->actingAs($this->superUser())
            ->from(cp_route('webhook-manager.outbound.create'))
            ->post(cp_route('webhook-manager.outbound.store'), $this->payload(['auth_config_json' => '']))
            ->assertSessionHasErrors('auth_config_json');

        $this->assertNull(OutboundWebhook::where('handle', 'signed-hook')->first());
    }

    public function test_unparsable_auth_config_json_is_rejected_instead_of_discarded(): void
    {
        $this->actingAs($this->superUser())
            ->from(cp_route('webhook-manager.outbound.create'))
            ->post(cp_route('webhook-manager.outbound.store'), $this->payload(['auth_config_json' => '{not json']))
            ->assertSessionHasErrors('auth_config_json');

        $this->assertNull(OutboundWebhook::where('handle', 'signed-hook')->first());
    }

    public function test_switching_to_no_auth_clears_the_stored_secret(): void
    {
        $user = $this->superUser();

        $this->actingAs($user)->post(cp_route('webhook-manager.outbound.store'), $this->payload());
        $hook = OutboundWebhook::where('handle', 'signed-hook')->firstOrFail();

        $this->actingAs($user)
            ->from(cp_route('webhook-manager.outbound.edit', $hook))
            ->patch(
                cp_route('webhook-manager.outbound.update', $hook),
                $this->payload(['auth_type' => 'none', 'auth_config_json' => ''])
            )
            ->assertRedirect();

        $this->assertSame([], $hook->fresh()->auth_config);
    }

    /**
     * Cross-brand guard: the update path now writes credentials, so it must
     * not be reachable for a hook belonging to another brand.
     */
    public function test_a_hook_from_another_brand_cannot_be_updated(): void
    {
        config()->set('brand-context.multi_brand', true);
        config()->set('brand-context.license_check', null);
        app('brand-context')->forget();

        $brandA = $this->makeBrand('brand-a');
        $brandB = $this->makeBrand('brand-b');

        $hookA = BrandContext::runFor($brandA, fn () => OutboundWebhook::create([
            'name' => 'A hook',
            'handle' => 'a-hook',
            'trigger_type' => 'entry.saved',
            'url' => 'https://a.example.test/hook',
            'auth_type' => 'hmac',
            'auth_config' => ['secret' => 'brand-a-secret'],
        ]));

        BrandContext::runFor($brandB, function () use ($hookA) {
            $this->actingAs($this->superUser())
                ->patch(
                    '/cp/webhook-manager/outbound/'.$hookA->id,
                    $this->payload(['handle' => 'a-hook', 'auth_config_json' => json_encode(['secret' => 'stolen'])])
                )
                ->assertNotFound();
        });

        $this->assertSame(
            ['secret' => 'brand-a-secret'],
            BrandContext::runFor($brandA, fn () => $hookA->fresh()->auth_config),
        );
    }

    private function makeBrand(string $handle): int
    {
        return (int) DB::table('brands')->insertGetId([
            'handle' => $handle,
            'name' => ucfirst($handle),
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
