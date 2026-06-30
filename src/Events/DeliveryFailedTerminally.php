<?php

namespace Goldnead\WebhookManager\Events;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a delivery has exhausted its retries and failed for good — the
 * hook for failure alerting. Carries the terminal Delivery (the webhook is
 * reachable via $delivery->outboundWebhook).
 */
class DeliveryFailedTerminally
{
    use Dispatchable;

    public function __construct(public Delivery $delivery)
    {
    }
}
