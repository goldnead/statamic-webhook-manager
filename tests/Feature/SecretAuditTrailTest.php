<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\BrandContext\Facades\BrandContext;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Services\Logging\AuditLogger;
use Goldnead\WebhookManager\Tests\CpTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * `webhook_secret_audits` shipped with a migration, a table and an
 * AuditLogger — and zero call sites. Creating or rotating a secret left no
 * trace whatsoever, which is precisely the question the table exists to
 * answer.
 *
 * What is recorded: the fact, the time, the actor, the auth scheme.
 * What is NEVER recorded: the secret, or any other value from the auth config.
 */
class SecretAuditTrailTest extends CpTestCase
{
    use RefreshDatabase;

    /** @return array<string,mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Audited hook',
            'handle' => 'audited-hook',
            'enabled' => true,
            'trigger_type' => 'entry.saved',
            'url' => 'https://receiver.example.test/hook',
            'method' => 'POST',
            'timeout_seconds' => 15,
            'follow_redirects' => true,
            'auth_type' => 'hmac',
            'auth_config_json' => json_encode(['secret' => 'audit-secret-one']),
            'payload_type' => 'raw_json',
            'payload_template' => '{"x":1}',
            'queue_enabled' => false,
            'log_body_mode' => 'partial',
        ], $overrides);
    }

    /** @return array<int,object> */
    private function audits(): array
    {
        return DB::table('webhook_secret_audits')->orderBy('id')->get()->all();
    }

    public function test_creating_and_rotating_a_secret_writes_an_audit_trail(): void
    {
        $user = $this->superUser();

        $this->actingAs($user)
            ->post(cp_route('webhook-manager.outbound.store'), $this->payload())
            ->assertRedirect();

        $hook = OutboundWebhook::where('handle', 'audited-hook')->firstOrFail();

        $audits = $this->audits();
        $this->assertCount(1, $audits, 'Setting a secret produced no audit entry.');
        $this->assertSame(AuditLogger::ACTION_CREATED, $audits[0]->action);
        $this->assertSame(AuditLogger::TARGET_OUTBOUND, $audits[0]->target_type);
        $this->assertSame((int) $hook->id, (int) $audits[0]->target_id);
        $this->assertSame('qa-user', $audits[0]->actor_id);
        $this->assertNotNull($audits[0]->created_at);

        // Rotation
        $this->actingAs($user)
            ->from(cp_route('webhook-manager.outbound.edit', $hook))
            ->patch(
                cp_route('webhook-manager.outbound.update', $hook),
                $this->payload(['auth_config_json' => json_encode(['secret' => 'audit-secret-two'])])
            )
            ->assertRedirect();

        $audits = $this->audits();
        $this->assertCount(2, $audits, 'Rotating a secret produced no audit entry.');
        $this->assertSame(AuditLogger::ACTION_ROTATED, $audits[1]->action);

        // Removal
        $this->actingAs($user)
            ->from(cp_route('webhook-manager.outbound.edit', $hook))
            ->patch(
                cp_route('webhook-manager.outbound.update', $hook),
                $this->payload(['auth_type' => 'none', 'auth_config_json' => ''])
            )
            ->assertRedirect();

        $audits = $this->audits();
        $this->assertCount(3, $audits);
        $this->assertSame(AuditLogger::ACTION_REMOVED, $audits[2]->action);
    }

    public function test_the_audit_trail_never_contains_the_secret(): void
    {
        $user = $this->superUser();

        $this->actingAs($user)->post(cp_route('webhook-manager.outbound.store'), $this->payload());
        $hook = OutboundWebhook::where('handle', 'audited-hook')->firstOrFail();

        $this->actingAs($user)
            ->from(cp_route('webhook-manager.outbound.edit', $hook))
            ->patch(
                cp_route('webhook-manager.outbound.update', $hook),
                $this->payload(['auth_config_json' => json_encode(['secret' => 'audit-secret-two'])])
            );

        $dump = json_encode($this->audits());

        $this->assertStringNotContainsString('audit-secret-one', $dump);
        $this->assertStringNotContainsString('audit-secret-two', $dump);

        // The scheme and the key names are useful and safe; the values are not.
        $this->assertStringContainsString('hmac', $dump);
        $this->assertStringContainsString('config_keys', $dump);
    }

    public function test_a_save_that_does_not_touch_the_secret_writes_nothing(): void
    {
        $user = $this->superUser();

        $this->actingAs($user)->post(cp_route('webhook-manager.outbound.store'), $this->payload());
        $hook = OutboundWebhook::where('handle', 'audited-hook')->firstOrFail();

        $this->actingAs($user)
            ->from(cp_route('webhook-manager.outbound.edit', $hook))
            ->patch(
                cp_route('webhook-manager.outbound.update', $hook),
                $this->payload(['auth_config_json' => '', 'name' => 'Renamed'])
            )
            ->assertRedirect();

        $this->assertCount(1, $this->audits(), 'A plain re-save must not add noise to the secret trail.');
    }

    public function test_a_direct_record_call_strips_credential_looking_context_keys(): void
    {
        $this->app->make(AuditLogger::class)->record(
            AuditLogger::ACTION_ROTATED,
            AuditLogger::TARGET_OUTBOUND,
            1,
            'someone',
            ['secret' => 'leaked-value', 'token' => 'also-leaked', 'auth_type' => 'hmac'],
        );

        $context = (string) $this->audits()[0]->context;

        $this->assertStringNotContainsString('leaked-value', $context);
        $this->assertStringNotContainsString('also-leaked', $context);
        $this->assertStringContainsString('hmac', $context);
    }

    /**
     * Cross-brand guard: the audit row belongs to the brand of the webhook it
     * describes, and must not surface for another brand.
     */
    public function test_audit_rows_carry_the_brand_of_their_webhook(): void
    {
        config()->set('brand-context.multi_brand', true);
        config()->set('brand-context.license_check', null);
        app('brand-context')->forget();

        $brandA = (int) DB::table('brands')->insertGetId([
            'handle' => 'brand-a', 'name' => 'Brand A', 'is_default' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $brandB = (int) DB::table('brands')->insertGetId([
            'handle' => 'brand-b', 'name' => 'Brand B', 'is_default' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $hookA = BrandContext::runFor($brandA, fn () => OutboundWebhook::create([
            'name' => 'A hook',
            'handle' => 'a-hook',
            'trigger_type' => 'entry.saved',
            'url' => 'https://a.example.test/hook',
            'auth_type' => 'hmac',
            'auth_config' => ['secret' => 'brand-a-secret'],
        ]));

        // Rotate under brand A's context, then read the trail from brand B's.
        BrandContext::runFor($brandA, function () use ($hookA) {
            $this->actingAs($this->superUser())
                ->from(cp_route('webhook-manager.outbound.edit', $hookA))
                ->patch(cp_route('webhook-manager.outbound.update', $hookA), [
                    'name' => 'A hook',
                    'handle' => 'a-hook',
                    'enabled' => true,
                    'trigger_type' => 'entry.saved',
                    'url' => 'https://a.example.test/hook',
                    'method' => 'POST',
                    'timeout_seconds' => 15,
                    'follow_redirects' => true,
                    'auth_type' => 'hmac',
                    'auth_config_json' => json_encode(['secret' => 'brand-a-rotated']),
                    'payload_type' => 'raw_json',
                    'payload_template' => '{"x":1}',
                    'queue_enabled' => false,
                    'log_body_mode' => 'partial',
                ])
                ->assertRedirect();
        });

        $rows = DB::table('webhook_secret_audits')->get();
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame($brandA, (int) $row->brand_id);
            $this->assertNotSame($brandB, (int) $row->brand_id);
        }
    }
}
