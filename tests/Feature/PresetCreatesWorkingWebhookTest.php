<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Domain\Delivery\Actions\CreateDeliverySnapshotAction;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Actions\CreateOutboundWebhookAction;
use Goldnead\WebhookManager\Presets\SlackPreset;
use Goldnead\WebhookManager\Services\DeliveryEngine;
use Goldnead\WebhookManager\Tests\TestCase;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class PresetCreatesWorkingWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_slack_preset_produces_a_webhook_that_delivers(): void
    {
        Http::fake([
            'https://hooks.slack.com/*' => Http::response(['ok' => true], 200),
        ]);

        $attributes = (new SlackPreset())->build([
            'name' => 'Notify on publish',
            'trigger_type' => 'entry.published',
            'webhook_url' => 'https://hooks.slack.com/services/T/B/x',
            'message' => 'New entry: {{ entry:title }}',
        ]);

        $hook = app(CreateOutboundWebhookAction::class)($attributes);

        $this->assertSame('slack', $hook->preset_handle);
        $this->assertSame('entry.published', $hook->trigger_type);

        $event = new TriggerEvent(
            triggerHandle: 'entry.published',
            sourceType: 'entry',
            sourceReference: '42',
            payload: ['id' => '42', 'title' => 'Hello world', 'site' => 'default'],
            site: 'default',
        );
        $context = new ExecutionContext($event);

        $delivery = app(CreateDeliverySnapshotAction::class)($hook, $context);
        $delivery = app(DeliveryEngine::class)->send($delivery);

        $this->assertSame(Delivery::STATUS_SUCCESS, $delivery->status);
        $this->assertSame(200, $delivery->response_status);
        $this->assertStringContainsString('"text": "New entry: Hello world"', $delivery->request_body);
    }
}
