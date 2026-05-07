<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Services\DeliveryMaskingService;
use Goldnead\WebhookManager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PermissionsRestrictSensitivePayloadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_headers_are_masked_for_users_without_permission(): void
    {
        config()->set('webhook-manager.logging.mask_headers', ['authorization', 'x-api-key']);
        config()->set('webhook-manager.logging.mask_payload_keys', ['secret', 'token']);

        $delivery = Delivery::create([
            'outbound_webhook_id' => 1,
            'trigger_type' => 'entry.published',
            'status' => Delivery::STATUS_SUCCESS,
            'request_url' => 'https://example.com',
            'request_method' => 'POST',
            'request_headers' => [
                'Authorization' => 'Bearer abcdefghij1234567890',
                'X-API-Key' => 'super-secret-key-123456',
            ],
            'request_body' => json_encode(['secret' => 'shh-very-secret', 'visible' => 'ok']),
        ]);

        $masker = new DeliveryMaskingService();

        $masked = $masker->maskForViewer($delivery, false);
        $this->assertStringNotContainsString('shh-very-secret', $masked->request_body);
        $this->assertStringNotContainsString('Bearer abcdefghij', json_encode($masked->request_headers));

        $unmasked = $masker->maskForViewer($delivery, true);
        $this->assertStringContainsString('shh-very-secret', $unmasked->request_body);
    }
}
