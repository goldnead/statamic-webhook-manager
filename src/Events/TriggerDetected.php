<?php

namespace Goldnead\WebhookManager\Events;

use Goldnead\WebhookManager\ValueObjects\TriggerEvent;

/**
 * Fired by listeners after they've normalised a framework event into a
 * TriggerEvent. The TriggerDispatcher service listens for this event to
 * resolve and execute matching outbound webhooks and rules.
 */
class TriggerDetected
{
    public function __construct(public readonly TriggerEvent $trigger)
    {
    }
}
