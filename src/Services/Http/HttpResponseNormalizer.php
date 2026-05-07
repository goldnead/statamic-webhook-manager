<?php

namespace Goldnead\WebhookManager\Services\Http;

use Goldnead\WebhookManager\Auth\Support\SecretMasker;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Illuminate\Support\Str;

/**
 * Translates raw HttpClient results into mutations on a Delivery model.
 */
class HttpResponseNormalizer
{
    public function applyToDelivery(Delivery $delivery, array $response, string $logMode): void
    {
        $delivery->response_status = $response['status'] ?? null;
        $delivery->response_headers = SecretMasker::maskHeaders(
            (array) ($response['headers'] ?? []),
            (array) config('webhook-manager.logging.mask_headers', []),
        );
        $body = $response['body'] ?? null;

        $delivery->response_body = match ($logMode) {
            'none' => null,
            'partial' => is_string($body)
                ? Str::limit($body, (int) config('webhook-manager.logging.partial_bytes', 4096), '… [truncated]')
                : null,
            default => $body,
        };

        $delivery->duration_ms = $response['duration_ms'] ?? $delivery->duration_ms;
    }
}
