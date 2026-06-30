<?php

namespace Goldnead\WebhookManager\Rules;

use Goldnead\WebhookManager\Domain\Rule\Models\Rule;
use Goldnead\WebhookManager\Contracts\Repositories\RuleRepositoryInterface;
use Goldnead\WebhookManager\Services\Logging\SystemLogger;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\ExecutionResult;

/**
 * Resolves rules whose trigger matches the current context, evaluates
 * each rule's condition tree, and runs the rule's action list when the
 * tree returns true.
 *
 * Returns one combined `ExecutionResult` per evaluated rule. The shape
 * is intentionally flat so callers (TriggerDispatcher, the CP debug
 * page, the test action) can treat it uniformly.
 */
class RuleEngine
{
    public function __construct(
        protected RuleRepositoryInterface $repository,
        protected ConditionEvaluator $conditions,
        protected ActionExecutor $actions,
        protected SystemLogger $logger,
    ) {
    }

    /**
     * Evaluate all enabled rules for the trigger in the context and execute
     * matching ones. Returns one ExecutionResult per matched rule whose
     * `data` array contains the per-action results.
     *
     * @return array<int, ExecutionResult>
     */
    public function evaluate(ExecutionContext $context): array
    {
        $rules = $this->repository->activeForTrigger($context->event->triggerHandle);

        $results = [];
        foreach ($rules as $rule) {
            $results[] = $this->evaluateOne($rule, $context);
        }

        return $results;
    }

    /**
     * Evaluate a single rule. Public so the CP "Test rule" action can
     * call it directly with a synthetic context without touching the
     * repository.
     */
    public function evaluateOne(Rule $rule, ExecutionContext $context): ExecutionResult
    {
        if (! $rule->enabled) {
            return ExecutionResult::ok('Rule skipped: disabled.', $this->meta($rule, false, []));
        }

        try {
            $matched = $this->conditions->evaluate($rule->conditions ?? null, $context);
        } catch (\Throwable $e) {
            $this->logger->error('rule_condition_exception', $e->getMessage(), [
                'rule_id' => $rule->id,
                'correlation_id' => $context->event->correlationId,
            ]);
            return ExecutionResult::fail("Condition evaluation threw: {$e->getMessage()}",
                $this->meta($rule, false, []));
        }

        if (! $matched) {
            return ExecutionResult::ok('Rule skipped: conditions not met.',
                $this->meta($rule, false, []));
        }

        $actionResults = $this->actions->run(
            $rule->actions ?? [],
            $context,
            (bool) $rule->stop_on_failure,
        );

        $allOk = ! empty($actionResults)
            && array_reduce($actionResults, fn (bool $carry, ExecutionResult $r) => $carry && $r->ok, true);

        $this->logger->info('rule_executed',
            "Rule '{$rule->handle}' executed (".count($actionResults).' actions, '
            .($allOk ? 'all ok' : 'with failures').').',
            [
                'rule_id' => $rule->id,
                'rule_handle' => $rule->handle,
                'trigger' => $context->event->triggerHandle,
                'correlation_id' => $context->event->correlationId,
            ]);

        $message = $allOk
            ? "Rule '{$rule->handle}' executed."
            : "Rule '{$rule->handle}' executed with failures.";

        return new ExecutionResult($allOk, $message, $this->meta($rule, true, $actionResults));
    }

    /**
     * @param  array<int, ExecutionResult>  $actionResults
     */
    protected function meta(Rule $rule, bool $matched, array $actionResults): array
    {
        return [
            'rule_id' => $rule->id,
            'rule_handle' => $rule->handle,
            'matched' => $matched,
            'actions' => array_map(fn (ExecutionResult $r) => [
                'ok' => $r->ok,
                'message' => $r->message,
                // Surface the FailureClassifier error_type at the top of
                // each per-action entry so the CP can render it as a
                // distinct badge without inspecting `data`.
                'error_type' => $r->data['error_type'] ?? null,
                'handle' => $r->data['handle'] ?? null,
                'data' => $r->data,
            ], $actionResults),
        ];
    }
}
