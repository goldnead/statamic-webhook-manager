<?php

namespace Goldnead\WebhookManager\Services;

use Goldnead\WebhookManager\Domain\OutboundWebhook\Actions\DispatchOutboundWebhookAction;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Queries\ResolveOutboundWebhookQuery;
use Goldnead\WebhookManager\Rules\RuleEngine;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;

/**
 * Glue between normalised TriggerDetected events and the queue/delivery
 * pipeline.
 *
 * 1. Build an ExecutionContext from the trigger
 * 2. Run the rule engine — rules may dispatch outbound webhooks of their
 *    own via the `send_outbound_webhook` action
 * 3. Resolve directly-attached outbound webhooks (matching trigger +
 *    own conditions) and snapshot+dispatch each one
 *
 * Rules and direct-attached outbound webhooks are intentionally separate
 * dispatch paths (PRD §39 REVIEW): Rules give "When→If→Then" composition,
 * direct outbound hooks remain the simple "fire on trigger" path that
 * works without authoring a rule.
 */
class TriggerDispatcher
{
    public function __construct(
        protected ResolveOutboundWebhookQuery $resolveOutbound,
        protected DispatchOutboundWebhookAction $dispatchOutbound,
        protected RuleEngine $rules,
    ) {
    }

    public function dispatch(TriggerEvent $trigger): void
    {
        $context = new ExecutionContext($trigger);

        // Rules may dispatch outbound webhooks of their own via the
        // send_outbound_webhook action. Direct hooks below run regardless.
        $this->rules->evaluate($context);

        $hooks = ($this->resolveOutbound)($context);
        foreach ($hooks as $hook) {
            $this->dispatchHook($hook, $context);
        }
    }

    protected function dispatchHook(OutboundWebhook $hook, ExecutionContext $context): void
    {
        ($this->dispatchOutbound)($hook, $context);
    }
}
