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

/**
 * Prüfung der "Wichtig"-Befunde aus TASKS/nacht-2026-11-17/stille-fehler-automations-webhooks.md
 * (statamic-webhook-manager, Punkt 5: Circuit-Breaker-Auslösung muss geloggt sein).
 */
class StilleFehlerWichtigPruefungTest extends TestCase
{
    use RefreshDatabase;

    private function hook(string $handle, string $url): OutboundWebhook
    {
        return OutboundWebhook::create([
            'name' => $handle,
            'handle' => $handle,
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'url' => $url,
            'method' => 'POST',
            'auth_type' => 'none',
            'payload_type' => 'raw_json',
            'payload_template' => '{"id":"{{ entry:id }}"}',
            'queue_enabled' => false,
        ]);
    }

    private function snapshot(OutboundWebhook $hook): Delivery
    {
        $context = new ExecutionContext(new TriggerEvent(
            triggerHandle: 'entry.published',
            sourceType: 'entry',
            sourceReference: '42',
            payload: ['id' => '42'],
            site: 'default',
        ));

        return ($this->app->make(CreateDeliverySnapshotAction::class))($hook, $context);
    }

    /**
     * Wichtig 5: Auslösung des Circuit Breakers muss im Log auftauchen (Webhook-ID).
     */
    public function test_tripping_the_breaker_leaves_a_log_entry_with_the_webhook_id(): void
    {
        config()->set('webhook-manager.circuit_breaker.threshold', 1);
        config()->set('webhook-manager.retry.max_attempts', 1);
        Http::fake(['https://example.com/dead' => Http::response('boom', 500)]);

        $hook = $this->hook('dead-endpoint', 'https://example.com/dead');
        $this->app->make(DeliveryEngine::class)->send($this->snapshot($hook));

        $this->assertFalse($hook->fresh()->enabled, 'Precondition: the breaker did not trip.');
        $entry = LogEntry::query()->where('type', 'circuit_breaker_tripped')->first();

        $this->assertNotNull($entry, 'Breaker trip is not in the system log at all.');
        $this->assertSame($hook->id, $entry->context['outbound_webhook_id'] ?? null, 'Trip log lacks the webhook id in its context.');
        $this->assertSame($hook->id, $entry->related_webhook_id, 'Trip log lacks related_webhook_id.');
    }
}
