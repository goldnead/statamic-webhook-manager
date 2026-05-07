<?php

namespace Goldnead\WebhookManager\Services;

use Goldnead\WebhookManager\Auth\Support\SecretMasker;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;

/**
 * Returns delivery snapshots safe for users without "view sensitive
 * payloads" permission — masking sensitive headers and known payload keys.
 */
class DeliveryMaskingService
{
    public function maskForViewer(Delivery $delivery, bool $canViewSensitive): Delivery
    {
        if ($canViewSensitive) {
            return $delivery;
        }

        $maskHeaders = (array) config('webhook-manager.logging.mask_headers', []);
        $maskKeys = (array) config('webhook-manager.logging.mask_payload_keys', []);

        $delivery = clone $delivery;
        $delivery->request_headers = SecretMasker::maskHeaders((array) $delivery->request_headers, $maskHeaders);
        $delivery->response_headers = SecretMasker::maskHeaders((array) $delivery->response_headers, $maskHeaders);

        if (is_string($delivery->request_body) && $this->looksLikeJson($delivery->request_body)) {
            $decoded = json_decode($delivery->request_body, true);
            if (is_array($decoded)) {
                $masked = SecretMasker::maskPayload($decoded, $maskKeys);
                $delivery->request_body = (string) json_encode($masked, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        return $delivery;
    }

    protected function looksLikeJson(string $body): bool
    {
        $trim = ltrim($body);
        return str_starts_with($trim, '{') || str_starts_with($trim, '[');
    }
}
