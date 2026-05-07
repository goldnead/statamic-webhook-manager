<?php

namespace Goldnead\WebhookManager\Services\Logging;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;

/**
 * Convenience wrapper for delivery-scoped log entries.
 */
class DeliveryLogger
{
    public function __construct(protected SystemLogger $system)
    {
    }

    public function failed(Delivery $delivery, string $reason): void
    {
        $this->system->warning('delivery_failed', $reason, [
            'delivery_id' => $delivery->id,
            'webhook_id' => $delivery->outbound_webhook_id,
            'correlation_id' => $delivery->correlation_id,
        ]);
    }

    public function success(Delivery $delivery): void
    {
        $this->system->info('delivery_success', "Delivery {$delivery->uuid} succeeded.", [
            'delivery_id' => $delivery->id,
            'webhook_id' => $delivery->outbound_webhook_id,
            'correlation_id' => $delivery->correlation_id,
        ]);
    }

    public function replayed(Delivery $delivery): void
    {
        $this->system->info('replay_executed', "Delivery {$delivery->uuid} replayed.", [
            'delivery_id' => $delivery->id,
            'webhook_id' => $delivery->outbound_webhook_id,
            'correlation_id' => $delivery->correlation_id,
        ]);
    }
}
