<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Domain\Delivery\Actions\CreateDeliverySnapshotAction;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Domain\Log\Models\LogEntry;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Services\DeliveryEngine;
use Goldnead\WebhookManager\Tests\TestCase;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class OutboundWebhookLogsFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_500_response_marks_the_delivery_as_failed_and_writes_a_log_entry(): void
    {
        Http::fake([
            'https://broken.example.com/*' => Http::response('boom', 500),
        ]);

        $hook = OutboundWebhook::create([
            'name' => 'Broken hook',
            'handle' => 'broken-hook',
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'url' => 'https://broken.example.com/x',
            'method' => 'POST',
            'auth_type' => 'none',
            'payload_type' => 'raw_json',
            'payload_template' => '{}',
            'queue_enabled' => false,
            'retry_strategy' => [
                'strategy' => 'exponential',
                'max_attempts' => 3,
                'retry_on_status' => [500],
            ],
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
        $this->assertSame(500, $delivery->response_status);
        $this->assertSame('server', $delivery->error_type);
        $this->assertNotNull($delivery->next_retry_at, 'A retry should be scheduled.');

        $this->assertTrue(LogEntry::where('type', 'delivery_failed')->exists());
    }
}
