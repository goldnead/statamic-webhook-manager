<?php

namespace Goldnead\WebhookManager\Domain\InboundEndpoint\Handlers;

use Goldnead\WebhookManager\Contracts\InboundActionHandlerInterface;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;

/**
 * No-op handler. Useful for endpoints that only need to log + acknowledge
 * a request (e.g. as a manual ingest gateway), without writing any data.
 */
class NoopHandler implements InboundActionHandlerInterface
{
    public function handle(): string
    {
        return 'noop';
    }

    public function label(): string
    {
        return 'Acknowledge only (no side effects)';
    }

    public function handleAction(InboundEndpoint $endpoint, array $mappedPayload, array $rawPayload): array
    {
        return [
            'ok' => true,
            'message' => 'Acknowledged.',
            'data' => $mappedPayload,
        ];
    }
}
