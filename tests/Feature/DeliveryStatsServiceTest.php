<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Services\DeliveryStatsService;
use Goldnead\WebhookManager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DeliveryStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function hook(string $name = 'Hook'): OutboundWebhook
    {
        return OutboundWebhook::create([
            'name' => $name, 'handle' => 'h-'.uniqid(), 'enabled' => true,
            'trigger_type' => 'entry.published', 'url' => 'https://example.com/'.uniqid(),
            'method' => 'POST', 'auth_type' => 'none', 'payload_type' => 'raw_json',
            'payload_template' => '{}',
        ]);
    }

    protected function delivery(OutboundWebhook $hook, string $status, array $extra = []): Delivery
    {
        return Delivery::create(array_merge([
            'outbound_webhook_id' => $hook->id,
            'trigger_type' => 'entry.published',
            'status' => $status,
            'request_url' => $hook->url,
            'request_method' => 'POST',
        ], $extra));
    }

    public function test_summary_counts_and_success_rate(): void
    {
        $hook = $this->hook();
        $this->delivery($hook, Delivery::STATUS_SUCCESS, ['duration_ms' => 100]);
        $this->delivery($hook, Delivery::STATUS_SUCCESS, ['duration_ms' => 200]);
        $this->delivery($hook, Delivery::STATUS_SUCCESS, ['duration_ms' => 300]);
        $this->delivery($hook, Delivery::STATUS_FAILED, ['duration_ms' => 50, 'error_type' => 'server']);

        $stats = app(DeliveryStatsService::class)->build(30);

        $this->assertSame(4, $stats['summary']['total']);
        $this->assertSame(3, $stats['summary']['success']);
        $this->assertSame(1, $stats['summary']['failed']);
        $this->assertSame(75.0, $stats['summary']['success_rate']);
    }

    public function test_timeseries_is_gap_filled_for_the_whole_window(): void
    {
        $hook = $this->hook();
        $this->delivery($hook, Delivery::STATUS_SUCCESS);

        $stats = app(DeliveryStatsService::class)->build(7);

        // One bucket per day, even on days with zero deliveries.
        $this->assertCount(7, $stats['timeseries']);
        $this->assertSame(1, array_sum(array_column($stats['timeseries'], 'total')));
    }

    public function test_latency_percentiles(): void
    {
        $hook = $this->hook();
        foreach ([10, 20, 30, 40, 50, 60, 70, 80, 90, 100] as $d) {
            $this->delivery($hook, Delivery::STATUS_SUCCESS, ['duration_ms' => $d]);
        }

        $latency = app(DeliveryStatsService::class)->build(30)['latency'];

        $this->assertSame(50, $latency['p50']);   // nearest-rank: ceil(0.5*10)=5 -> index 4 -> 50
        $this->assertSame(100, $latency['p95']);
        $this->assertSame(100, $latency['max']);
    }

    public function test_error_breakdown_and_top_failing(): void
    {
        $a = $this->hook('Alpha');
        $b = $this->hook('Beta');
        $this->delivery($a, Delivery::STATUS_FAILED, ['error_type' => 'server']);
        $this->delivery($a, Delivery::STATUS_FAILED, ['error_type' => 'server']);
        $this->delivery($a, Delivery::STATUS_FAILED, ['error_type' => 'timeout']);
        $this->delivery($b, Delivery::STATUS_FAILED, ['error_type' => 'server']);

        $stats = app(DeliveryStatsService::class)->build(30);

        $errors = collect($stats['errors'])->keyBy('type');
        $this->assertSame(3, $errors['server']['count']);
        $this->assertSame(1, $errors['timeout']['count']);

        // Alpha has 3 failures, Beta 1 → Alpha ranked first.
        $this->assertSame('Alpha', $stats['top_failing'][0]['name']);
        $this->assertSame(3, $stats['top_failing'][0]['failures']);
    }

    public function test_webhook_filter_scopes_to_a_single_hook(): void
    {
        $a = $this->hook('Alpha');
        $b = $this->hook('Beta');
        $this->delivery($a, Delivery::STATUS_SUCCESS);
        $this->delivery($b, Delivery::STATUS_FAILED, ['error_type' => 'server']);

        $stats = app(DeliveryStatsService::class)->build(30, $a->id);

        $this->assertSame(1, $stats['summary']['total']);
        $this->assertSame(0, $stats['summary']['failed']);
    }
}
