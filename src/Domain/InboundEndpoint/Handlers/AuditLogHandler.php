<?php

namespace Goldnead\WebhookManager\Domain\InboundEndpoint\Handlers;

use Goldnead\WebhookManager\Contracts\InboundActionHandlerInterface;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Goldnead\WebhookManager\Services\Logging\SystemLogger;

/**
 * Writes an audit log entry containing the mapped payload. Useful for
 * staging an inbound integration before turning on a destructive handler,
 * or as a passive listener for events that should be captured but not
 * acted on.
 */
class AuditLogHandler implements InboundActionHandlerInterface
{
    public function handle(): string
    {
        return 'audit_log';
    }

    public function label(): string
    {
        return 'Write audit log entry';
    }

    public function handleAction(InboundEndpoint $endpoint, array $mappedPayload, array $rawPayload): array
    {
        try {
            /** @var SystemLogger $logger */
            $logger = app(SystemLogger::class);
            $entry = $logger->info('inbound_audit', "Inbound payload received on {$endpoint->handle}", [
                'endpoint_id' => $endpoint->id,
                'handle' => $endpoint->handle,
                'mapped' => $mappedPayload,
            ]);

            return [
                'ok' => true,
                'message' => 'Audit log written.',
                'data' => ['log_id' => $entry->id ?? null],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Failed to write audit log: '.$e->getMessage(),
                'data' => [],
            ];
        }
    }
}
