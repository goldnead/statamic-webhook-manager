<?php

namespace Goldnead\WebhookManager\Services\Inbound;

use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Goldnead\WebhookManager\Registries\InboundActionHandlerRegistry;
use Goldnead\WebhookManager\Services\Logging\SystemLogger;

/**
 * Resolves the configured `action_type` against the handler registry and
 * delegates execution. Catches handler exceptions, logs them, and returns
 * a uniform `{ok, message, data}` response so the caller doesn't have to
 * deal with mixed exception/return semantics.
 */
class InboundActionDispatcher
{
    public function __construct(
        protected InboundActionHandlerRegistry $registry,
        protected SystemLogger $logger,
    ) {
    }

    /**
     * @return array{ok:bool, message:string, data:array}
     */
    public function dispatch(InboundEndpoint $endpoint, array $mappedPayload, array $rawPayload): array
    {
        $actionType = (string) ($endpoint->action_type ?? 'noop');
        $handler = $this->registry->get($actionType);

        if (! $handler) {
            $this->logger->error('inbound_action_handler_missing', "No handler for inbound action type '{$actionType}'", [
                'endpoint_id' => $endpoint->id,
                'action_type' => $actionType,
            ]);

            return [
                'ok' => false,
                'message' => "No handler registered for action type '{$actionType}'.",
                'data' => [],
            ];
        }

        try {
            $result = $handler->handleAction($endpoint, $mappedPayload, $rawPayload);

            if (! ($result['ok'] ?? false)) {
                $this->logger->warning('inbound_action_failed', $result['message'] ?? 'Inbound action failed', [
                    'endpoint_id' => $endpoint->id,
                    'action_type' => $actionType,
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('inbound_action_exception', $e->getMessage(), [
                'endpoint_id' => $endpoint->id,
                'action_type' => $actionType,
                'exception' => get_class($e),
            ]);

            return [
                'ok' => false,
                'message' => "Inbound action threw: {$e->getMessage()}",
                'data' => [],
            ];
        }
    }
}
