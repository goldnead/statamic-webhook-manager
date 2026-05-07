<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Domain\Delivery\Actions\CreateDeliverySnapshotAction;
use Goldnead\WebhookManager\Domain\Delivery\Actions\ReplayDeliveryAction;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Services\DeliveryEngine;
use Goldnead\WebhookManager\Tests\TestCase;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class ReplayFailedDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_replaying_a_failed_delivery_reruns_it(): void
    {
        // First call fails, second call (the replay) succeeds.
        Http::fakeSequence()
            ->push('boom', 500)
            ->push(['ok' => true], 200);

        $hook = OutboundWebhook::create([
            'name' => 'Replay test',
            'handle' => 'replay-test',
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'url' => 'https://replay.example.com',
            'method' => 'POST',
            'auth_type' => 'none',
            'payload_type' => 'raw_json',
            'payload_template' => '{"x":1}',
            'queue_enabled' => false,
        ]);

        $event = new TriggerEvent('entry.published', 'entry', '1', ['id' => '1']);
        $context = new ExecutionContext($event);

        /** @var CreateDeliverySnapshotAction $snap */
        $snap = $this->app->make(CreateDeliverySnapshotAction::class);
        $delivery = ($snap)($hook, $context);

        /** @var DeliveryEngine $engine */
        $engine = $this->app->make(DeliveryEngine::class);
        $delivery = $engine->send($delivery);
        $this->assertSame(Delivery::STATUS_FAILED, $delivery->status);

        /** @var ReplayDeliveryAction $replay */
        $replay = $this->app->make(ReplayDeliveryAction::class);
        $delivery = $replay($delivery);

        $this->assertSame(Delivery::STATUS_SUCCESS, $delivery->status);
        $this->assertSame(2, $delivery->attempts);
    }
}
