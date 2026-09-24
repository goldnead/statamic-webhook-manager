<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Services\Http\HttpRequestFactory;
use Goldnead\WebhookManager\Tests\TestCase;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The idempotency key has to reach the receiver.
 *
 * With "Idempotenz" switched on, the manager computed a key and stored it on
 * the delivery row, where only the operator could see it. The receiver, the
 * one party that could drop a duplicate, never got it. It now travels as
 * `Idempotency-Key`, and every request carries `X-Webhook-Id` with the same
 * value, so a receiver can dedupe even without the switch. When the event
 * brings its own `event_id`, that id is the key: it is stable across a
 * re-dispatch of the same event, which a hash over the dispatch time is not.
 */
class IdempotencyKeyHeaderTest extends TestCase
{
    use RefreshDatabase;

    private function hook(bool $idempotent, array $headers = []): OutboundWebhook
    {
        return OutboundWebhook::create([
            'name' => 'Hook',
            'handle' => 'hook-'.uniqid(),
            'enabled' => true,
            'trigger_type' => 'payments.paid',
            'url' => 'https://receiver.example.test/hook',
            'method' => 'POST',
            'auth_type' => 'none',
            'payload_type' => 'raw_json',
            'idempotency_enabled' => $idempotent,
            'headers' => $headers,
        ]);
    }

    private function context(array $payload): ExecutionContext
    {
        return new ExecutionContext(new TriggerEvent(
            triggerHandle: 'payments.paid',
            sourceType: 'payments',
            sourceReference: '2201',
            payload: $payload,
        ));
    }

    public function test_the_key_is_sent_as_idempotency_key_header(): void
    {
        $request = $this->app->make(HttpRequestFactory::class)
            ->build($this->hook(true), $this->context(['id' => 2201]));

        $this->assertNotEmpty($request['idempotency_key']);
        $this->assertSame($request['idempotency_key'], $request['headers']['Idempotency-Key'] ?? null);
        $this->assertSame($request['idempotency_key'], $request['headers']['X-Webhook-Id'] ?? null);
    }

    public function test_an_event_id_in_the_payload_is_the_key(): void
    {
        $request = $this->app->make(HttpRequestFactory::class)
            ->build($this->hook(true), $this->context(['event_id' => 'evt_01J9', 'id' => 2201]));

        $this->assertSame('evt_01J9', $request['idempotency_key']);
        $this->assertSame('evt_01J9', $request['headers']['Idempotency-Key']);
    }

    public function test_without_the_switch_only_the_webhook_id_is_sent(): void
    {
        $request = $this->app->make(HttpRequestFactory::class)
            ->build($this->hook(false), $this->context(['event_id' => 'evt_02', 'id' => 1]));

        $this->assertNull($request['idempotency_key']);
        $this->assertArrayNotHasKey('Idempotency-Key', $request['headers']);
        $this->assertSame('evt_02', $request['headers']['X-Webhook-Id'] ?? null);
    }

    public function test_a_header_the_operator_set_is_not_overwritten(): void
    {
        $request = $this->app->make(HttpRequestFactory::class)
            ->build($this->hook(true, ['Idempotency-Key' => 'mine']), $this->context(['id' => 1]));

        $this->assertSame('mine', $request['headers']['Idempotency-Key']);
    }

    public function test_the_stored_snapshot_carries_the_header_so_retries_resend_it(): void
    {
        $hook = $this->hook(true);
        $factory = $this->app->make(HttpRequestFactory::class);
        $request = $factory->build($hook, $this->context(['event_id' => 'evt_03']));

        $delivery = new Delivery;
        $factory->maskedSnapshot($request, $hook, $delivery);

        $this->assertSame('evt_03', $delivery->request_headers['Idempotency-Key'] ?? null);
        $this->assertSame('evt_03', $delivery->idempotency_key);
    }
}
