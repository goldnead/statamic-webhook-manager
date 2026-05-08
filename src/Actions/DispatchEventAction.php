<?php

namespace Goldnead\WebhookManager\Actions;

use Goldnead\WebhookManager\Contracts\ActionInterface;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\ExecutionResult;
use Illuminate\Support\Facades\Event;

/**
 * Dispatch a Laravel event with the trigger context.
 *
 * Rule config:
 *   - `event` (string, required) — fully-qualified event class OR string event name
 *   - `payload` (array, optional) — extra data added alongside the trigger payload
 *
 * If the configured `event` is a class that exists, it is instantiated with
 * the resolved payload and the trigger event as constructor args. Otherwise
 * the value is treated as a string event name and dispatched.
 */
class DispatchEventAction implements ActionInterface
{
    public function handle(): string
    {
        return 'dispatch_event';
    }

    public function label(): string
    {
        return 'Dispatch internal event';
    }

    public function execute(array $config, ExecutionContext $context): ExecutionResult
    {
        $eventName = (string) ($config['event'] ?? '');
        if ($eventName === '') {
            return ExecutionResult::fail('Missing required config.event.');
        }

        $payload = array_merge(
            $context->payload(),
            is_array($config['payload'] ?? null) ? $config['payload'] : [],
        );

        try {
            if (class_exists($eventName)) {
                Event::dispatch(new $eventName($payload, $context->event));
            } else {
                Event::dispatch($eventName, [$payload, $context->event]);
            }

            return ExecutionResult::ok('Event dispatched.', ['event' => $eventName]);
        } catch (\Throwable $e) {
            return ExecutionResult::fail('Failed to dispatch event: '.$e->getMessage());
        }
    }
}
