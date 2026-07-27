<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\BrandContext\Facades\BrandContext;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Tests\CpTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Delivery detail page + replay endpoint.
 *
 * Two separate defects met on this screen:
 *
 *   - The Replay button POSTed via Inertia, but the controller answered with
 *     a bare JSON body. Inertia cannot consume that, fell back to a hard
 *     `window.location` visit of the same URL — a GET against a POST-only
 *     route — and the user landed on a 404 while the replay had in fact run.
 *   - Correlation ID, trigger, attempt count and the pre-computed cURL line
 *     never reached the template, so the debug view was missing exactly the
 *     fields one opens it for.
 */
class DeliveryDetailAndReplayTest extends CpTestCase
{
    use RefreshDatabase;

    private function makeDelivery(array $overrides = []): Delivery
    {
        $hook = OutboundWebhook::create([
            'name' => 'Detail hook',
            'handle' => 'detail-hook',
            'enabled' => true,
            'trigger_type' => 'entry.saved',
            'url' => 'https://receiver.example.test/hook',
            'method' => 'POST',
            'auth_type' => 'none',
            'payload_type' => 'raw_json',
            'payload_template' => '{"x":1}',
            'queue_enabled' => false,
        ]);

        return Delivery::create(array_merge([
            'outbound_webhook_id' => $hook->id,
            'trigger_type' => 'entry.saved',
            'status' => Delivery::STATUS_SUCCESS,
            'correlation_id' => 'a2da5566-bd8b-4985-a756-5a65e7d0d13c',
            'request_url' => 'https://receiver.example.test/hook',
            'request_method' => 'POST',
            'request_headers' => ['Content-Type' => 'application/json'],
            'request_body' => '{"x":1}',
            'response_status' => 200,
            // PSR-7 shape: the value is an ARRAY. This is what took the
            // Response panel down in the browser.
            'response_headers' => ['content-type' => ['application/json']],
            'response_body' => '{"ok":true}',
            'duration_ms' => 3,
            'attempts' => 1,
        ], $overrides));
    }

    public function test_the_detail_payload_carries_correlation_trigger_attempts_and_curl(): void
    {
        $delivery = $this->makeDelivery();

        $response = $this->actingAs($this->superUser())
            ->withHeaders($this->inertiaHeaders())
            ->get(cp_route('webhook-manager.deliveries.show', $delivery));

        $response->assertOk();
        $payload = $response->json('props.delivery');

        $this->assertSame('a2da5566-bd8b-4985-a756-5a65e7d0d13c', $payload['correlation_id']);
        $this->assertSame('entry.saved', $payload['trigger_type']);
        $this->assertArrayHasKey('trigger_label', $payload);
        $this->assertSame(1, $payload['attempts']);
        $this->assertSame(3, $payload['duration_ms']);
        $this->assertSame(200, $payload['response_code']);

        $this->assertNotEmpty($payload['curl']);
        $this->assertStringContainsString('curl -X POST', $payload['curl']);
        $this->assertStringContainsString('https://receiver.example.test/hook', $payload['curl']);
    }

    /**
     * The array-valued Content-Type must survive the controller unchanged —
     * this is the exact shape the Vue panel has to cope with.
     */
    public function test_response_headers_reach_the_view_in_their_psr7_array_shape(): void
    {
        $delivery = $this->makeDelivery();

        $payload = $this->actingAs($this->superUser())
            ->withHeaders($this->inertiaHeaders())
            ->get(cp_route('webhook-manager.deliveries.show', $delivery))
            ->json('props.delivery');

        $this->assertSame(['application/json'], $payload['response']['headers']['content-type']);
        $this->assertSame('{"ok":true}', $payload['response']['body']);
    }

    public function test_an_inertia_replay_redirects_instead_of_returning_bare_json(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $delivery = $this->makeDelivery(['status' => Delivery::STATUS_FAILED, 'response_status' => 500]);
        $showUrl = cp_route('webhook-manager.deliveries.show', $delivery);

        $response = $this->actingAs($this->superUser())
            ->from($showUrl)
            ->withHeaders($this->inertiaHeaders())
            ->post(cp_route('webhook-manager.actions.replay-delivery', $delivery));

        // Anything that is not a redirect makes Inertia hard-navigate to the
        // POST-only URL and land the user on a 404.
        $response->assertRedirect($showUrl);
        $response->assertSessionHas('success');
    }

    public function test_a_plain_replay_still_answers_json(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $delivery = $this->makeDelivery(['status' => Delivery::STATUS_FAILED, 'response_status' => 500]);

        $response = $this->actingAs($this->superUser())
            ->postJson(cp_route('webhook-manager.actions.replay-delivery', $delivery));

        $response->assertOk();
        $this->assertTrue($response->json('ok'));
    }

    /** Cross-brand guard for the delivery detail + replay routes. */
    public function test_a_delivery_from_another_brand_is_not_visible_or_replayable(): void
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

        $deliveryA = BrandContext::runFor($brandA, fn () => $this->makeDelivery([
            'status' => Delivery::STATUS_FAILED,
            'response_status' => 500,
        ]));

        BrandContext::runFor($brandB, function () use ($deliveryA) {
            $this->actingAs($this->superUser())
                ->withHeaders($this->inertiaHeaders())
                ->get('/cp/webhook-manager/deliveries/'.$deliveryA->id)
                ->assertNotFound();

            $this->actingAs($this->superUser())
                ->postJson('/cp/webhook-manager/deliveries/'.$deliveryA->id.'/replay')
                ->assertNotFound();
        });

        Http::assertNothingSent();
    }
}
