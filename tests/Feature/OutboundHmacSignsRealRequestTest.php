<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Auth\Support\SignatureGenerator;
use Goldnead\WebhookManager\Domain\Delivery\Actions\CreateDeliverySnapshotAction;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Services\DeliveryEngine;
use Goldnead\WebhookManager\Tests\CpTestCase;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

/**
 * End-to-end proof for the secret fix: a hook configured through the CP form
 * must produce a request that is actually SIGNED, and after a rotation it must
 * be signed with the NEW secret and no longer verify against the old one.
 *
 * Storing the secret is only half the claim — the half that matters to a
 * receiver is the byte on the wire. So the signature is recomputed here
 * independently and compared against the header the HTTP client really sent.
 */
class OutboundHmacSignsRealRequestTest extends CpTestCase
{
    use RefreshDatabase;

    /** @return array<string,mixed> */
    private function payload(string $secret): array
    {
        return [
            'name' => 'Signed hook',
            'handle' => 'signed-hook',
            'enabled' => true,
            'trigger_type' => 'entry.saved',
            'url' => 'https://receiver.example.test/hook',
            'method' => 'POST',
            'timeout_seconds' => 15,
            'follow_redirects' => true,
            'auth_type' => 'hmac',
            'auth_config_json' => json_encode(['secret' => $secret]),
            'payload_type' => 'raw_json',
            'payload_template' => '{"x":1}',
            'queue_enabled' => false,
            'log_body_mode' => 'full',
        ];
    }

    /** Fires one delivery and returns the headers + body that went on the wire. */
    private function deliver(OutboundWebhook $hook): array
    {
        $sent = [];
        Http::fake(function ($request) use (&$sent) {
            $sent[] = [
                'headers' => $request->headers(),
                'body' => $request->body(),
            ];

            return Http::response(['ok' => true], 200, ['Content-Type' => ['application/json']]);
        });

        $context = new ExecutionContext(new TriggerEvent('entry.saved', 'entry', '1', ['id' => '1']));
        $delivery = ($this->app->make(CreateDeliverySnapshotAction::class))($hook, $context);
        $delivery = $this->app->make(DeliveryEngine::class)->send($delivery);

        $this->assertSame(Delivery::STATUS_SUCCESS, $delivery->status);
        $this->assertCount(1, $sent);

        return $sent[0];
    }

    private function headerValue(array $headers, string $name): string
    {
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === strtolower($name)) {
                return is_array($value) ? (string) reset($value) : (string) $value;
            }
        }

        return '';
    }

    public function test_a_hook_configured_through_the_cp_signs_its_requests(): void
    {
        $this->actingAs($this->superUser())
            ->post(cp_route('webhook-manager.outbound.store'), $this->payload('first-secret'))
            ->assertRedirect();

        $hook = OutboundWebhook::where('handle', 'signed-hook')->firstOrFail();
        $wire = $this->deliver($hook);

        $signature = $this->headerValue($wire['headers'], 'X-Webhook-Signature');
        $timestamp = $this->headerValue($wire['headers'], 'X-Webhook-Timestamp');

        $this->assertNotSame('', $signature, 'The request went out UNSIGNED although the hook is configured as HMAC.');
        $this->assertNotSame('', $timestamp);

        $expected = 'sha256='.SignatureGenerator::compute($timestamp.'.'.$wire['body'], 'first-secret');
        $this->assertSame($expected, $signature);
    }

    public function test_after_a_rotation_the_wire_signature_uses_the_new_secret(): void
    {
        $user = $this->superUser();

        $this->actingAs($user)->post(cp_route('webhook-manager.outbound.store'), $this->payload('first-secret'));
        $hook = OutboundWebhook::where('handle', 'signed-hook')->firstOrFail();

        $this->actingAs($user)
            ->from(cp_route('webhook-manager.outbound.edit', $hook))
            ->patch(cp_route('webhook-manager.outbound.update', $hook), $this->payload('rotated-secret'))
            ->assertRedirect();

        $wire = $this->deliver($hook->fresh());

        $signature = $this->headerValue($wire['headers'], 'X-Webhook-Signature');
        $timestamp = $this->headerValue($wire['headers'], 'X-Webhook-Timestamp');
        $payload = $timestamp.'.'.$wire['body'];

        $this->assertSame(
            'sha256='.SignatureGenerator::compute($payload, 'rotated-secret'),
            $signature,
            'Rotation reported success but the receiver still gets the old signature.'
        );
        $this->assertNotSame(
            'sha256='.SignatureGenerator::compute($payload, 'first-secret'),
            $signature,
            'The old secret still signs outgoing requests after a rotation.'
        );
    }
}
