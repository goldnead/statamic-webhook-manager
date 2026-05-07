<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Auth\Support\SignatureGenerator;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Goldnead\WebhookManager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InboundEndpointRejectsInvalidSignatureTest extends TestCase
{
    use RefreshDatabase;

    protected function makeHmacEndpoint(string $handle, string $secret): InboundEndpoint
    {
        return InboundEndpoint::create([
            'name' => 'HMAC endpoint',
            'handle' => $handle,
            'enabled' => true,
            'path' => $handle,
            'allowed_methods' => ['POST'],
            'auth_type' => 'hmac',
            'auth_config' => [
                'secret' => $secret,
                'algorithm' => 'sha256',
                // require_timestamp left off so the test can sign without
                // worrying about the timestamp header window.
            ],
            'expected_content_type' => 'application/json',
            'action_type' => 'noop',
        ]);
    }

    public function test_inbound_request_with_valid_signature_passes(): void
    {
        $secret = 'shared-secret';
        $endpoint = $this->makeHmacEndpoint('hmac-valid', $secret);

        $body = json_encode(['hello' => 'world']);
        $signature = SignatureGenerator::compute($body, $secret, 'sha256');

        $response = $this->call(
            'POST',
            "/!/webhooks/inbound/{$endpoint->handle}",
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
            ],
            $body,
        );

        $response->assertStatus(200);
    }

    public function test_inbound_request_with_wrong_signature_is_rejected_401(): void
    {
        $endpoint = $this->makeHmacEndpoint('hmac-bad', 'shared-secret');

        $body = json_encode(['hello' => 'world']);

        $response = $this->call(
            'POST',
            "/!/webhooks/inbound/{$endpoint->handle}",
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WEBHOOK_SIGNATURE' => 'this-is-not-the-real-signature',
            ],
            $body,
        );

        $response->assertStatus(401)
            ->assertJson(['ok' => false]);
    }

    public function test_inbound_request_without_signature_header_is_rejected_401(): void
    {
        $endpoint = $this->makeHmacEndpoint('hmac-none', 'shared-secret');

        $response = $this->postJson(
            "/!/webhooks/inbound/{$endpoint->handle}",
            ['hello' => 'world'],
        );

        $response->assertStatus(401);
    }

    public function test_static_header_endpoint_rejects_wrong_value_401(): void
    {
        InboundEndpoint::create([
            'name' => 'Static header',
            'handle' => 'static',
            'enabled' => true,
            'path' => 'static',
            'allowed_methods' => ['POST'],
            'auth_type' => 'static_header',
            'auth_config' => [
                'header' => 'X-API-Key',
                'value' => 'correct-key',
            ],
            'expected_content_type' => 'application/json',
            'action_type' => 'noop',
        ]);

        $response = $this->postJson('/!/webhooks/inbound/static', ['x' => 1], [
            'X-API-Key' => 'wrong-key',
        ]);

        $response->assertStatus(401);
    }
}
