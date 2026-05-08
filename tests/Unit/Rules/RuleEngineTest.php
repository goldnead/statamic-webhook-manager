<?php

namespace Goldnead\WebhookManager\Tests\Unit\Rules;

use Goldnead\WebhookManager\Domain\Rule\Models\Rule;
use Goldnead\WebhookManager\Rules\RuleEngine;
use Goldnead\WebhookManager\Tests\TestCase;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\ExecutionResult;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Exercises the RuleEngine.evaluateOne path with the real
 * ConditionEvaluator + ActionExecutor + ActionRegistry
 * (which boots the built-in `write_log_note` action).
 */
class RuleEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function context(string $trigger, array $payload): ExecutionContext
    {
        return new ExecutionContext(new TriggerEvent(
            triggerHandle: $trigger,
            sourceType: 'test',
            sourceReference: '1',
            payload: $payload,
            site: 'default',
        ));
    }

    public function test_disabled_rule_is_skipped(): void
    {
        $rule = new Rule([
            'name' => 'Disabled rule',
            'handle' => 'disabled-rule',
            'enabled' => false,
            'trigger_type' => 'entry.published',
            'actions' => [
                ['handle' => 'write_log_note', 'config' => ['message' => 'should not run']],
            ],
        ]);

        $engine = $this->app->make(RuleEngine::class);
        $result = $engine->evaluateOne($rule, $this->context('entry.published', []));

        $this->assertTrue($result->ok);
        $this->assertSame('Rule skipped: disabled.', $result->message);
        $this->assertFalse($result->data['matched']);
    }

    public function test_rule_with_failing_conditions_is_skipped(): void
    {
        $rule = new Rule([
            'name' => 'Approved only',
            'handle' => 'approved-only',
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'conditions' => [
                'field' => 'data.status', 'op' => 'equals', 'value' => 'approved',
            ],
            'actions' => [
                ['handle' => 'write_log_note', 'config' => ['message' => 'never runs']],
            ],
        ]);

        $engine = $this->app->make(RuleEngine::class);
        $result = $engine->evaluateOne($rule, $this->context('entry.published', ['data' => ['status' => 'draft']]));

        $this->assertTrue($result->ok);
        $this->assertSame('Rule skipped: conditions not met.', $result->message);
        $this->assertFalse($result->data['matched']);
    }

    public function test_matching_rule_executes_actions_in_order(): void
    {
        $rule = new Rule([
            'name' => 'Approved entries',
            'handle' => 'approved-entries',
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'conditions' => [
                'field' => 'data.status', 'op' => 'equals', 'value' => 'approved',
            ],
            'actions' => [
                ['handle' => 'write_log_note', 'config' => ['message' => 'first']],
                ['handle' => 'write_log_note', 'config' => ['message' => 'second']],
            ],
        ]);

        $engine = $this->app->make(RuleEngine::class);
        $result = $engine->evaluateOne(
            $rule,
            $this->context('entry.published', ['data' => ['status' => 'approved']]),
        );

        $this->assertTrue($result->ok);
        $this->assertTrue($result->data['matched']);
        $this->assertCount(2, $result->data['actions']);
        $this->assertTrue($result->data['actions'][0]['ok']);
        $this->assertTrue($result->data['actions'][1]['ok']);
    }

    public function test_unknown_action_handle_fails_gracefully(): void
    {
        $rule = new Rule([
            'name' => 'Unknown',
            'handle' => 'unknown',
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'actions' => [
                ['handle' => 'this_handle_does_not_exist', 'config' => []],
            ],
        ]);

        $engine = $this->app->make(RuleEngine::class);
        $result = $engine->evaluateOne($rule, $this->context('entry.published', []));

        $this->assertFalse($result->ok);
        $this->assertCount(1, $result->data['actions']);
        $this->assertFalse($result->data['actions'][0]['ok']);
        $this->assertStringContainsString('Unknown action', $result->data['actions'][0]['message']);

        // The unknown-handle path is classified as a configuration error
        // and the resolved handle is echoed back so the CP can render it.
        $this->assertSame('configuration', $result->data['actions'][0]['error_type']);
        $this->assertSame('this_handle_does_not_exist', $result->data['actions'][0]['handle']);
    }

    public function test_handler_returning_clean_fail_is_classified_as_payload(): void
    {
        // create_entry returns a clean ExecutionResult::fail when its
        // required `collection` config is missing — that's the
        // canonical "missing config field" path the executor should
        // classify as PAYLOAD.
        $rule = new Rule([
            'name' => 'Bad config',
            'handle' => 'bad-config',
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'actions' => [
                ['handle' => 'create_entry', 'config' => []],
            ],
        ]);

        $engine = $this->app->make(RuleEngine::class);
        $result = $engine->evaluateOne($rule, $this->context('entry.published', []));

        $this->assertFalse($result->ok);
        $this->assertSame('payload', $result->data['actions'][0]['error_type']);
        $this->assertSame('create_entry', $result->data['actions'][0]['handle']);
    }

    public function test_stop_on_failure_short_circuits(): void
    {
        $rule = new Rule([
            'name' => 'Halt on first failure',
            'handle' => 'halt',
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'stop_on_failure' => true,
            'actions' => [
                ['handle' => 'this_does_not_exist', 'config' => []],
                ['handle' => 'write_log_note', 'config' => ['message' => 'should not run']],
            ],
        ]);

        $engine = $this->app->make(RuleEngine::class);
        $result = $engine->evaluateOne($rule, $this->context('entry.published', []));

        $this->assertFalse($result->ok);
        $this->assertCount(1, $result->data['actions'], 'Second action should be short-circuited.');
    }
}
