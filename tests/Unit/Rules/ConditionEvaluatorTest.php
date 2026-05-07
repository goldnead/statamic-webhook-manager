<?php

namespace Goldnead\WebhookManager\Tests\Unit\Rules;

use Goldnead\WebhookManager\Rules\ConditionEvaluator;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;
use PHPUnit\Framework\TestCase;

class ConditionEvaluatorTest extends TestCase
{
    private function context(array $payload, ?string $site = 'default'): ExecutionContext
    {
        $event = new TriggerEvent(
            triggerHandle: 'entry.published',
            sourceType: 'entry',
            sourceReference: $payload['id'] ?? null,
            payload: $payload,
            site: $site,
        );
        return new ExecutionContext($event);
    }

    public function test_returns_true_when_no_conditions(): void
    {
        $eval = new ConditionEvaluator();
        $this->assertTrue($eval->evaluate([], $this->context([])));
        $this->assertTrue($eval->evaluate(null, $this->context([])));
    }

    public function test_evaluates_and_logic(): void
    {
        $eval = new ConditionEvaluator();
        $tree = [
            'logic' => 'and',
            'conditions' => [
                ['field' => 'collection', 'op' => 'equals', 'value' => 'posts'],
                ['field' => 'site', 'op' => 'equals', 'value' => 'default'],
            ],
        ];

        $this->assertTrue($eval->evaluate($tree, $this->context(['collection' => 'posts'])));
        $this->assertFalse($eval->evaluate($tree, $this->context(['collection' => 'pages'])));
    }

    public function test_evaluates_or_logic_with_in_operator(): void
    {
        $eval = new ConditionEvaluator();
        $tree = [
            'logic' => 'or',
            'conditions' => [
                ['field' => 'collection', 'op' => 'in', 'value' => ['posts', 'news']],
                ['field' => 'data.featured', 'op' => 'equals', 'value' => true],
            ],
        ];

        $this->assertTrue($eval->evaluate($tree, $this->context(['collection' => 'news'])));
        $this->assertTrue($eval->evaluate($tree, $this->context(['data' => ['featured' => true]])));
        $this->assertFalse($eval->evaluate($tree, $this->context(['collection' => 'pages', 'data' => ['featured' => false]])));
    }
}
