<?php

namespace Goldnead\WebhookManager\Domain\InboundEndpoint\Handlers;

use Goldnead\WebhookManager\Contracts\InboundActionHandlerInterface;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Illuminate\Support\Facades\Event;

/**
 * Dispatches an internal Laravel event with the mapped payload.
 *
 * Endpoint `action_config` must include:
 *   - `event` (string, required) — fully-qualified event class OR string event name
 *
 * If the configured `event` is a class that exists, it is instantiated with
 * the mapped payload as its single constructor argument. Otherwise the value
 * is treated as a string event name and dispatched with the payload.
 *
 * This is the recommended extension point for custom integrations that
 * shouldn't ship as inbound action handlers themselves.
 */
class DispatchEventHandler implements InboundActionHandlerInterface
{
    public function handle(): string
    {
        return 'dispatch_event';
    }

    public function label(): string
    {
        return 'Dispatch internal event';
    }

    public function handleAction(InboundEndpoint $endpoint, array $mappedPayload, array $rawPayload): array
    {
        $config = $endpoint->action_config ?? [];
        $eventName = (string) ($config['event'] ?? '');

        if ($eventName === '') {
            return [
                'ok' => false,
                'message' => 'Missing required action_config.event.',
                'data' => [],
            ];
        }

        try {
            if (class_exists($eventName)) {
                Event::dispatch(new $eventName($mappedPayload, $rawPayload, $endpoint));
            } else {
                Event::dispatch($eventName, [$mappedPayload, $rawPayload, $endpoint]);
            }

            return [
                'ok' => true,
                'message' => 'Event dispatched.',
                'data' => ['event' => $eventName],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Failed to dispatch event: '.$e->getMessage(),
                'data' => [],
            ];
        }
    }
}
