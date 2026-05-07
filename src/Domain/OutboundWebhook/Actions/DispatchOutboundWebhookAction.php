<?php

namespace Goldnead\WebhookManager\Domain\OutboundWebhook\Actions;

use Goldnead\WebhookManager\Domain\Delivery\Actions\CreateDeliverySnapshotAction;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Jobs\ProcessOutboundDeliveryJob;
use Goldnead\WebhookManager\Services\DeliveryEngine;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;

/**
 * Snapshot + dispatch a single outbound hook for an execution context.
 *
 * Extracted from `Services\TriggerDispatcher::dispatchHook` so the same
 * code path can be invoked from the rule engine's
 * `SendOutboundWebhookAction` without duplicating queue/sync logic.
 */
class DispatchOutboundWebhookAction
{
    public function __construct(
        protected CreateDeliverySnapshotAction $snapshot,
        protected DeliveryEngine $engine,
    ) {
    }

    /**
     * @return int Delivery id (regardless of sync/queued mode)
     */
    public function __invoke(OutboundWebhook $hook, ExecutionContext $context): int
    {
        $delivery = ($this->snapshot)($hook, $context);

        if ($hook->isQueueEnabled()) {
            ProcessOutboundDeliveryJob::dispatch($delivery->id)
                ->onConnection(config('webhook-manager.queue.connection'))
                ->onQueue(config('webhook-manager.queue.name', 'default'));

            return $delivery->id;
        }

        $sent = $this->engine->send($delivery);
        return $sent->id;
    }
}
