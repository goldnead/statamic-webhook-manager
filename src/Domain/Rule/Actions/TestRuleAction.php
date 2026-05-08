<?php

namespace Goldnead\WebhookManager\Domain\Rule\Actions;

use Goldnead\WebhookManager\Domain\Rule\Models\Rule;
use Goldnead\WebhookManager\Rules\RuleEngine;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\ExecutionResult;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;

/**
 * Run a single rule against a synthetic trigger payload — used by the
 * CP "Test rule" button. Bypasses the trigger registry entirely so users
 * can iterate on conditions/actions without wiring up a real event.
 *
 * Note: the underlying actions still execute for real (an entry will be
 * created, an outbound webhook will be sent). This matches outbound's
 * Test action behaviour and is the most useful semantics for debugging
 * — dry-run mode is a v2 candidate.
 */
class TestRuleAction
{
    public function __construct(protected RuleEngine $engine)
    {
    }

    /**
     * @return ExecutionResult The combined ExecutionResult from RuleEngine::evaluateOne.
     */
    public function __invoke(Rule $rule, array $samplePayload, ?string $site = null): ExecutionResult
    {
        $event = new TriggerEvent(
            triggerHandle: $rule->trigger_type,
            sourceType: 'test',
            sourceReference: 'cp-test',
            payload: $samplePayload,
            site: $site,
            isReplay: false,
            eventAt: new \DateTimeImmutable(),
        );

        return $this->engine->evaluateOne($rule, new ExecutionContext($event));
    }
}
