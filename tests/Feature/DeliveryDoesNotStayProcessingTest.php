<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Domain\Delivery\Actions\CreateDeliverySnapshotAction;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Events\DeliveryFailedTerminally;
use Goldnead\WebhookManager\Jobs\ProcessOutboundDeliveryJob;
use Goldnead\WebhookManager\Services\DeliveryEngine;
use Goldnead\WebhookManager\Services\Http\HttpResponseNormalizer;
use Goldnead\WebhookManager\Tests\TestCase;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * Eine Delivery steht vor dem Senden auf `processing`. Bricht danach etwas ab
 * (Wurf nach dem HTTP-Call, Worker-Kill), darf sie dort nicht für immer stehen
 * bleiben: ohne `next_retry_at` holt sie sonst nichts mehr zurück und kein
 * Alarm feuert.
 */
class DeliveryDoesNotStayProcessingTest extends TestCase
{
    use RefreshDatabase;

    private function delivery(): Delivery
    {
        $hook = OutboundWebhook::create([
            'name' => 'Hook',
            'handle' => 'processing-hook',
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'url' => 'https://ok.example.com/x',
            'method' => 'POST',
            'auth_type' => 'none',
            'payload_type' => 'raw_json',
            'payload_template' => '{}',
            'queue_enabled' => false,
        ]);

        $context = new ExecutionContext(new TriggerEvent('entry.published', 'entry', '1', ['id' => '1']));

        return ($this->app->make(CreateDeliverySnapshotAction::class))($hook, $context);
    }

    public function test_a_throw_after_the_http_call_ends_in_failed_not_processing(): void
    {
        Http::fake(['https://ok.example.com/*' => Http::response('ok', 200)]);
        Event::fake([DeliveryFailedTerminally::class]);

        $this->app->bind(HttpResponseNormalizer::class, fn () => new class extends HttpResponseNormalizer
        {
            public function applyToDelivery(Delivery $delivery, array $response, string $logMode): void
            {
                throw new \RuntimeException('normalizer exploded');
            }
        });

        $delivery = $this->delivery();
        $result = $this->app->make(DeliveryEngine::class)->send($delivery);

        $this->assertSame(Delivery::STATUS_FAILED, $result->fresh()->status);
        $this->assertStringContainsString('normalizer exploded', (string) $result->fresh()->error_message);
        Event::assertDispatched(DeliveryFailedTerminally::class);
    }

    public function test_job_failed_hook_closes_a_delivery_left_on_processing(): void
    {
        Event::fake([DeliveryFailedTerminally::class]);

        $delivery = $this->delivery();
        $delivery->status = Delivery::STATUS_PROCESSING;
        $delivery->save();

        (new ProcessOutboundDeliveryJob($delivery->id))->failed(new \RuntimeException('worker killed'));

        $this->assertSame(Delivery::STATUS_FAILED, $delivery->fresh()->status);
        Event::assertDispatched(DeliveryFailedTerminally::class);
    }

    public function test_job_failed_hook_leaves_a_finished_delivery_alone(): void
    {
        Event::fake([DeliveryFailedTerminally::class]);

        $delivery = $this->delivery();
        $delivery->status = Delivery::STATUS_SUCCESS;
        $delivery->save();

        (new ProcessOutboundDeliveryJob($delivery->id))->failed(new \RuntimeException('late'));

        $this->assertSame(Delivery::STATUS_SUCCESS, $delivery->fresh()->status);
        Event::assertNotDispatched(DeliveryFailedTerminally::class);
    }
}
