<?php

namespace Goldnead\WebhookManager\Listeners;

use Goldnead\WebhookManager\Events\TriggerDetected;
use Goldnead\WebhookManager\Services\TriggerDispatcher;

/**
 * Listens for normalised TriggerDetected events and hands them off to the
 * TriggerDispatcher service which resolves matching outbound webhooks
 * (and, eventually, rules) and dispatches deliveries onto the queue.
 */
class DispatchTriggerListener
{
    public function __construct(protected TriggerDispatcher $dispatcher)
    {
    }

    public function handle(TriggerDetected $event): void
    {
        $this->dispatcher->dispatch($event->trigger);
    }
}
