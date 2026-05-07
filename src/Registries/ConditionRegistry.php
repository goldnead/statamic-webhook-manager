<?php

namespace Goldnead\WebhookManager\Registries;

use Goldnead\WebhookManager\Contracts\ConditionInterface;

/**
 * TODO: REVIEW — defaults will be added once the condition language stabilises.
 * For the first pass, conditions are evaluated by the (very simple)
 * Rules\ConditionEvaluator directly off JSON config.
 */
class ConditionRegistry
{
    /** @var array<string, ConditionInterface> */
    protected array $conditions = [];

    public function register(ConditionInterface $condition): void
    {
        $this->conditions[$condition->handle()] = $condition;
    }

    public function get(string $handle): ?ConditionInterface
    {
        return $this->conditions[$handle] ?? null;
    }

    public function all(): array
    {
        return $this->conditions;
    }
}
