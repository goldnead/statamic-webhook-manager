<?php

namespace Goldnead\WebhookManager\Rules;

use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\ExecutionResult;

/**
 * TODO: REVIEW — first pass is a no-op stub. Outbound webhooks are
 * dispatched directly via Services\TriggerDispatcher; the rule engine
 * itself ships in a later iteration.
 *
 * The interface here is intentionally compatible with the eventual
 * implementation so callers don't need to be rewritten.
 */
class RuleEngine
{
    /**
     * Evaluate all enabled rules against the given context.
     *
     * @return array<int, ExecutionResult>
     */
    public function evaluate(ExecutionContext $context): array
    {
        // TODO: REVIEW — load rules via RuleRepository, evaluate conditions,
        // run actions in order respecting `stop_on_failure`, return per-action
        // ExecutionResult entries.
        return [];
    }
}
