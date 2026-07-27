<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\BrandContext\Facades\BrandContext;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Tests\CpTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Statamic\Facades\Permission;

/**
 * The "Test" button could not be reached from the CP at all, for two reasons
 * stacked on top of each other:
 *
 *   1. `can('test outbound webhooks') ?? can('manage outbound webhooks')` —
 *      `??` only fires on NULL, and `can()` returns a boolean. The fallback
 *      was dead code, so the flag was whatever the first call returned.
 *   2. …and the ability `test outbound webhooks` was never registered, so it
 *      answered `false` for everyone, super users included.
 *
 * The endpoint itself was fine the whole time — which is the worst combination,
 * because nothing anywhere reported an error.
 */
class OutboundTestActionIsReachableTest extends CpTestCase
{
    use RefreshDatabase;

    private function makeHook(array $overrides = []): OutboundWebhook
    {
        return OutboundWebhook::create(array_merge([
            'name' => 'Testable hook',
            'handle' => 'testable-hook',
            'enabled' => true,
            'trigger_type' => 'entry.saved',
            'url' => 'https://receiver.example.test/hook',
            'method' => 'POST',
            'auth_type' => 'none',
            'payload_type' => 'raw_json',
            'payload_template' => '{"x":1}',
            'queue_enabled' => false,
        ], $overrides));
    }

    public function test_the_dedicated_ability_is_registered(): void
    {
        $handles = collect(Permission::all())->map(fn ($p) => $p->value())->all();

        $this->assertContains(
            'test outbound webhooks',
            $handles,
            'An unregistered ability answers false for everyone — including super users.'
        );
    }

    public function test_a_user_who_may_only_manage_still_sees_the_test_button(): void
    {
        $hook = $this->makeHook();

        $response = $this->actingAs($this->cpUser(['view webhooks', 'manage outbound webhooks']))
            ->withHeaders($this->inertiaHeaders())
            ->get(cp_route('webhook-manager.outbound.edit', $hook));

        $response->assertOk();
        $props = $response->json('props');

        $this->assertTrue($props['canTest'], 'The Test button stays hidden for a user who may manage the hook.');
        $this->assertNotNull($props['testUrl']);
    }

    public function test_the_listing_row_exposes_the_test_flag(): void
    {
        $this->makeHook();

        $response = $this->actingAs($this->cpUser(['view webhooks', 'manage outbound webhooks']))
            ->withHeaders($this->inertiaHeaders())
            ->get(cp_route('webhook-manager.outbound.index'));

        $response->assertOk();
        $row = $response->json('props.webhooks.data.0');

        $this->assertTrue($row['can_test']);
        $this->assertNotNull($row['test_url']);
    }

    public function test_the_dedicated_ability_alone_is_enough_to_fire_a_test(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $hook = $this->makeHook();

        $response = $this->actingAs($this->cpUser(['view webhooks', 'test outbound webhooks']))
            ->postJson(cp_route('webhook-manager.actions.test-outbound', $hook), ['sample_payload' => []]);

        $response->assertOk();
        $this->assertTrue($response->json('ok'));
        $this->assertSame(Delivery::STATUS_SUCCESS, $response->json('status'));
        Http::assertSentCount(1);
    }

    public function test_a_read_only_user_is_refused(): void
    {
        $hook = $this->makeHook();

        $this->actingAs($this->cpUser(['view webhooks', 'view webhook deliveries']))
            ->postJson(cp_route('webhook-manager.actions.test-outbound', $hook), ['sample_payload' => []])
            ->assertForbidden();
    }

    /** Cross-brand guard for the ability we just widened. */
    public function test_a_hook_from_another_brand_cannot_be_tested(): void
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

        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $hookA = BrandContext::runFor($brandA, fn () => $this->makeHook(['handle' => 'brand-a-hook']));

        BrandContext::runFor($brandB, function () use ($hookA) {
            $this->actingAs($this->superUser())
                ->postJson('/cp/webhook-manager/outbound/'.$hookA->id.'/test', ['sample_payload' => []])
                ->assertNotFound();
        });

        Http::assertNothingSent();
    }
}
