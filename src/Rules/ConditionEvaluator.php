<?php

namespace Goldnead\WebhookManager\Rules;

use Goldnead\WebhookManager\ValueObjects\ExecutionContext;

/**
 * Evaluates a JSON-style condition tree.
 *
 * Format:
 * {
 *   "logic": "and",            // and | or
 *   "conditions": [
 *     { "field": "data.status", "op": "equals", "value": "approved" },
 *     { "field": "site",        "op": "equals", "value": "default" },
 *     {                         // nested group
 *       "logic": "or",
 *       "conditions": [...]
 *     }
 *   ]
 * }
 *
 * Supported ops: equals, not_equals, in, not_in, contains, exists,
 * empty, gt, gte, lt, lte, regex.
 */
class ConditionEvaluator
{
    public function evaluate(?array $tree, ExecutionContext $context): bool
    {
        if (empty($tree)) {
            return true;
        }
        return $this->evaluateNode($tree, $context);
    }

    protected function evaluateNode(array $node, ExecutionContext $context): bool
    {
        if (isset($node['conditions']) && is_array($node['conditions'])) {
            $logic = strtolower($node['logic'] ?? 'and');
            $results = array_map(fn ($child) => $this->evaluateNode($child, $context), $node['conditions']);
            return $logic === 'or' ? in_array(true, $results, true) : ! in_array(false, $results, true);
        }

        // Leaf condition
        $field = (string) ($node['field'] ?? '');
        $op = strtolower((string) ($node['op'] ?? 'equals'));
        $expected = $node['value'] ?? null;
        $actual = $this->resolveField($field, $context);

        return $this->matches($op, $actual, $expected);
    }

    protected function resolveField(string $field, ExecutionContext $context): mixed
    {
        // Allow shortcuts: "site", "trigger", "replay"
        return match ($field) {
            'site' => $context->event->site,
            'locale' => $context->event->locale,
            'trigger' => $context->event->triggerHandle,
            'replay' => $context->event->isReplay,
            default => $context->field($field),
        };
    }

    protected function matches(string $op, mixed $actual, mixed $expected): bool
    {
        return match ($op) {
            'equals' => $actual == $expected, // loose; explicit type check via type_equals
            'not_equals' => $actual != $expected,
            'in' => is_array($expected) && in_array($actual, $expected, true),
            'not_in' => is_array($expected) && ! in_array($actual, $expected, true),
            'contains' => is_string($actual) && is_string($expected) && str_contains($actual, $expected),
            'exists' => $actual !== null,
            'empty' => $actual === null || $actual === '' || $actual === [] || $actual === false,
            'gt' => is_numeric($actual) && is_numeric($expected) && $actual > $expected,
            'gte' => is_numeric($actual) && is_numeric($expected) && $actual >= $expected,
            'lt' => is_numeric($actual) && is_numeric($expected) && $actual < $expected,
            'lte' => is_numeric($actual) && is_numeric($expected) && $actual <= $expected,
            'regex' => is_string($actual) && is_string($expected) && @preg_match($expected, $actual) === 1,
            default => false,
        };
    }
}
