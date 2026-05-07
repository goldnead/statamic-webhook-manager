<?php

namespace Goldnead\WebhookManager\Services;

use Goldnead\WebhookManager\Domain\Delivery\Actions\CreateDeliverySnapshotAction;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Queries\ResolveOutboundWebhookQuery;
use Goldnead\WebhookManager\Jobs\ProcessOutboundDeliveryJob;
use Goldnead\WebhookManager\Rules\RuleEngine;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;

/**
 * Glue between normalised TriggerDetected events and the queue/delivery
 * pipeline.
 *
 * 1. Build an ExecutionContext from the trigger
 * 2. Resolve matching outbound webhooks (trigger + conditions)
 * 3. Create a Delivery snapshot for each
 * 4. Either dispatch onto the queue or send synchronously
 *
 * The RuleEngine is invoked for parity with the future iteration but its
 * `evaluate()` is a no-op for now (TODO: REVIEW).
 */
class TriggerDispatcher
{
    public function __construct(
        protected ResolveOutboundWebhookQuery $resolveOutbound,
        protected CreateDeliverySnapshotAction $snapshot,
        protected DeliveryEngine $engine,
        protected RuleEngine $rules,
    ) {
    }

    public function dispatch(TriggerEvent $trigger): void
    {
        $context = new ExecutionContext($trigger);

        // TODO: REVIEW — evaluating rules first will allow rules to short-circuit
        // outbound execution once the engine ships.
        $this->rules->evaluate($context);

        $hooks = ($this->resolveOutbound)($context);
        foreach ($hooks as $hook) {
            $this->dispatchHook($hook, $context);
        }
    }

    protected function dispatchHook(OutboundWebhook $hook, ExecutionContext $context): void
    {
        $delivery = ($this->snapshot)($hook, $context);

        if ($hook->isQueueEnabled()) {
            ProcessOutboundDeliveryJob::dispatch($delivery->id)
                ->onConnection(config('webhook-manager.queue.connection'))
                ->onQueue(config('webhook-manager.queue.name', 'default'));
            return;
        }

        $this->engine->send($delivery);
    }
}
