<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\BrandContext\Facades\BrandContext;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Hard brand isolation (multi-brand mode) for Webhook Manager.
 *
 * A webhook endpoint / delivery created under brand A must be completely
 * invisible from brand B's context — no cross-brand leak through the models.
 */
class BrandIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable multi-brand AFTER boot so the brand-context CP wiring (which
        // touches Statamic facades not booted in this testbench env) stays off;
        // the BrandScope reads this flag live at query time, so isolation is
        // still fully active for the assertions below.
        config()->set('brand-context.multi_brand', true);
        config()->set('brand-context.license_check', null);

        // Fresh manager state between resolutions.
        app('brand-context')->forget();
    }

    private function makeBrand(string $handle, string $name): int
    {
        return (int) DB::table('brands')->insertGetId([
            'handle' => $handle,
            'name' => $name,
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_outbound_webhook_and_delivery_are_isolated_across_brands(): void
    {
        $brandA = $this->makeBrand('brand-a', 'Brand A');
        $brandB = $this->makeBrand('brand-b', 'Brand B');

        // --- Brand A objects ---
        [$hookA, $deliveryA] = BrandContext::runFor($brandA, function () {
            $hook = OutboundWebhook::create([
                'name' => 'A hook',
                'handle' => 'shared-handle',
                'trigger_type' => 'entry.published',
                'url' => 'https://a.example.test/hook',
            ]);

            $delivery = Delivery::create([
                'outbound_webhook_id' => $hook->id,
                'trigger_type' => 'entry.published',
                'status' => Delivery::STATUS_SUCCESS,
                'request_url' => 'https://a.example.test/hook',
                'request_method' => 'POST',
            ]);

            return [$hook, $delivery];
        });

        // --- Brand B objects (same handle, must be allowed) ---
        [$hookB, $deliveryB] = BrandContext::runFor($brandB, function () {
            $hook = OutboundWebhook::create([
                'name' => 'B hook',
                'handle' => 'shared-handle',
                'trigger_type' => 'entry.published',
                'url' => 'https://b.example.test/hook',
            ]);

            $delivery = Delivery::create([
                'outbound_webhook_id' => $hook->id,
                'trigger_type' => 'entry.published',
                'status' => Delivery::STATUS_SUCCESS,
                'request_url' => 'https://b.example.test/hook',
                'request_method' => 'POST',
            ]);

            return [$hook, $delivery];
        });

        // Sanity: brand_id was stamped on create.
        $this->assertSame($brandA, (int) $hookA->brand_id);
        $this->assertSame($brandB, (int) $hookB->brand_id);
        $this->assertSame($brandA, (int) $deliveryA->brand_id);
        $this->assertSame($brandB, (int) $deliveryB->brand_id);

        // --- From brand A's context: only A is visible ---
        BrandContext::setCurrent($brandA);

        $this->assertSame(1, OutboundWebhook::count());
        $this->assertNotNull(OutboundWebhook::find($hookA->id));
        $this->assertNull(OutboundWebhook::find($hookB->id), 'Brand B webhook leaked into brand A');

        $this->assertSame(1, Delivery::count());
        $this->assertNotNull(Delivery::find($deliveryA->id));
        $this->assertNull(Delivery::find($deliveryB->id), 'Brand B delivery leaked into brand A');

        // --- From brand B's context: only B is visible ---
        BrandContext::setCurrent($brandB);

        $this->assertSame(1, OutboundWebhook::count());
        $this->assertNotNull(OutboundWebhook::find($hookB->id));
        $this->assertNull(OutboundWebhook::find($hookA->id), 'Brand A webhook leaked into brand B');
    }

    public function test_same_handle_is_allowed_in_two_brands(): void
    {
        $brandA = $this->makeBrand('brand-a', 'Brand A');
        $brandB = $this->makeBrand('brand-b', 'Brand B');

        BrandContext::runFor($brandA, fn () => OutboundWebhook::create([
            'name' => 'A', 'handle' => 'dupe', 'trigger_type' => 'entry.published', 'url' => 'https://a.test',
        ]));

        // Same handle under a different brand must NOT violate uniqueness.
        BrandContext::runFor($brandB, fn () => OutboundWebhook::create([
            'name' => 'B', 'handle' => 'dupe', 'trigger_type' => 'entry.published', 'url' => 'https://b.test',
        ]));

        $this->assertSame(2, OutboundWebhook::withoutGlobalScopes()->where('handle', 'dupe')->count());
    }
}
