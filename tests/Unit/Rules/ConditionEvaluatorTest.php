<?php

namespace Goldnead\WebhookManager\Tests\Unit\Rules;

use Goldnead\WebhookManager\Rules\ConditionEvaluator;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;
use PHPUnit\Framework\TestCase;

class ConditionEvaluatorTest extends TestCase
{
    protected function context(array $payload, ?string $site = 'default'): ExecutionContext
    {
        $event = new TriggerEvent(
            triggerHandle: 'entry.published',
            sourceType: 'entry',
            sourceReference: '1',
            payload: $payload,
            site: $site,
        );
        return new ExecutionContext($event);
    }

    public function test_empty_tree_matches(): void
    {
        $this->assertTrue((new ConditionEvaluator())->evaluate(null, $this->context([])));
        $this->assertTrue((new ConditionEvaluator())->evaluate([], $this->context([])));
    }

    public function test_leaf_equals_matches_loosely(): void
    {
        $tree = ['field' => 'data.status', 'op' => 'equals', 'value' => 'approved'];
        $ok = (new ConditionEvaluator())->evaluate($tree, $this->context(['data' => ['status' => 'approved']]));
        $this->assertTrue($ok);
    }

    public function test_and_group_requires_all_children(): void
    {
        $tree = [
            'logic' => 'and',
            'conditions' => [
                ['field' => 'data.status', 'op' => 'equals', 'value' => 'approved'],
                ['field' => 'site', 'op' => 'equals', 'value' => 'default'],
            ],
        ];
        $context = $this->context(['data' => ['status' => 'approved']]);
        $this->assertTrue((new ConditionEvaluator())->evaluate($tree, $context));

        $context = $this->context(['data' => ['status' => 'approved']], site: 'de');
        $this->assertFalse((new ConditionEvaluator())->evaluate($tree, $context));
    }

    public function test_or_group_requires_any_child(): void
    {
        $tree = [
            'logic' => 'or',
            'conditions' => [
                ['field' => 'data.status', 'op' => 'equals', 'value' => 'approved'],
                ['field' => 'data.priority', 'op' => 'equals', 'value' => 'high'],
            ],
        ];
        $context = $this->context(['data' => ['status' => 'draft', 'priority' => 'high']]);
        $this->assertTrue((new ConditionEvaluator())->evaluate($tree, $context));

        $context = $this->context(['data' => ['status' => 'draft', 'priority' => 'low']]);
        $this->assertFalse((new ConditionEvaluator())->evaluate($tree, $context));
    }

    public function test_nested_groups(): void
    {
        $tree = [
            'logic' => 'and',
            'conditions' => [
                ['field' => 'site', 'op' => 'equals', 'value' => 'default'],
                [
                    'logic' => 'or',
                    'conditions' => [
                        ['field' => 'data.tag', 'op' => 'equals', 'value' => 'news'],
                        ['field' => 'data.tag', 'op' => 'equals', 'value' => 'press'],
                    ],
                ],
            ],
        ];
        $this->assertTrue((new ConditionEvaluator())->evaluate(
            $tree, $this->context(['data' => ['tag' => 'news']]),
        ));
        $this->assertFalse((new ConditionEvaluator())->evaluate(
            $tree, $this->context(['data' => ['tag' => 'tutorial']]),
        ));
    }

    public function test_in_and_not_in(): void
    {
        $context = $this->context(['data' => ['category' => 'a']]);
        $this->assertTrue((new ConditionEvaluator())->evaluate(
            ['field' => 'data.category', 'op' => 'in', 'value' => ['a', 'b']],
            $context,
        ));
        $this->assertFalse((new ConditionEvaluator())->evaluate(
            ['field' => 'data.category', 'op' => 'not_in', 'value' => ['a', 'b']],
            $context,
        ));
    }

    public function test_contains_exists_empty(): void
    {
        $context = $this->context(['data' => ['email' => 'a@example.com']]);
        $this->assertTrue((new ConditionEvaluator())->evaluate(
            ['field' => 'data.email', 'op' => 'contains', 'value' => '@example'],
            $context,
        ));
        $this->assertTrue((new ConditionEvaluator())->evaluate(
            ['field' => 'data.email', 'op' => 'exists'],
            $context,
        ));
        $this->assertFalse((new ConditionEvaluator())->evaluate(
            ['field' => 'data.email', 'op' => 'empty'],
            $context,
        ));
    }

    public function test_numeric_comparisons(): void
    {
        $context = $this->context(['data' => ['count' => 42]]);
        $eval = new ConditionEvaluator();
        $this->assertTrue($eval->evaluate(['field' => 'data.count', 'op' => 'gt', 'value' => 10], $context));
        $this->assertTrue($eval->evaluate(['field' => 'data.count', 'op' => 'gte', 'value' => 42], $context));
        $this->assertTrue($eval->evaluate(['field' => 'data.count', 'op' => 'lt', 'value' => 100], $context));
        $this->assertTrue($eval->evaluate(['field' => 'data.count', 'op' => 'lte', 'value' => 42], $context));
        $this->assertFalse($eval->evaluate(['field' => 'data.count', 'op' => 'gt', 'value' => 100], $context));
    }

    public function test_regex(): void
    {
        $context = $this->context(['data' => ['slug' => 'my-cool-post']]);
        $this->assertTrue((new ConditionEvaluator())->evaluate(
            ['field' => 'data.slug', 'op' => 'regex', 'value' => '/^my-/'],
            $context,
        ));
    }

    public function test_field_shortcuts_site_locale_trigger_replay(): void
    {
        $event = new TriggerEvent(
            triggerHandle: 'entry.published',
            sourceType: 'entry',
            sourceReference: '1',
            payload: [],
            site: 'default',
            locale: 'en',
            isReplay: true,
        );
        $context = new ExecutionContext($event);
        $eval = new ConditionEvaluator();

        $this->assertTrue($eval->evaluate(['field' => 'site', 'op' => 'equals', 'value' => 'default'], $context));
        $this->assertTrue($eval->evaluate(['field' => 'locale', 'op' => 'equals', 'value' => 'en'], $context));
        $this->assertTrue($eval->evaluate(['field' => 'trigger', 'op' => 'equals', 'value' => 'entry.published'], $context));
        $this->assertTrue($eval->evaluate(['field' => 'replay', 'op' => 'equals', 'value' => true], $context));
    }
}
