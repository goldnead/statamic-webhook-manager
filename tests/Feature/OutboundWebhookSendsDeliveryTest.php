<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Domain\Delivery\Actions\CreateDeliverySnapshotAction;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Services\DeliveryEngine;
use Goldnead\WebhookManager\Tests\TestCase;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class OutboundWebhookSendsDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_successful_outbound_request_is_recorded_as_a_delivery(): void
    {
        Http::fake([
            'https://example.com/webhook' => Http::response(['ok' => true], 200),
        ]);

        $hook = OutboundWebhook::create([
            'name' => 'Send on entry publish',
            'handle' => 'on-entry-publish',
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'url' => 'https://example.com/webhook',
            'method' => 'POST',
            'auth_type' => 'none',
            'payload_type' => 'raw_json',
            'payload_template' => '{"id":"{{ entry:id }}","title":"{{ entry:title }}"}',
            'queue_enabled' => false,
        ]);

        $event = new TriggerEvent(
            triggerHandle: 'entry.published',
            sourceType: 'entry',
            sourceReference: '42',
            payload: ['id' => '42', 'title' => 'Hello world', 'site' => 'default'],
            site: 'default',
        );
        $context = new ExecutionContext($event);

        /** @var CreateDeliverySnapshotAction $snapshot */
        $snapshot = $this->app->make(CreateDeliverySnapshotAction::class);
        $delivery = ($snapshot)($hook, $context);

        /** @var DeliveryEngine $engine */
        $engine = $this->app->make(DeliveryEngine::class);
        $delivery = $engine->send($delivery);

        $this->assertSame(Delivery::STATUS_SUCCESS, $delivery->status);
        $this->assertSame(200, $delivery->response_status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertStringContainsString('"id":"42"', $delivery->request_body);
    }
}
