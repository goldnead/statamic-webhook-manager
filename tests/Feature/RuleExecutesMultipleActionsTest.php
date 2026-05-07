<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Domain\Log\Models\LogEntry;
use Goldnead\WebhookManager\Domain\Rule\Models\Rule;
use Goldnead\WebhookManager\Services\TriggerDispatcher;
use Goldnead\WebhookManager\Tests\TestCase;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * End-to-end: a normalised TriggerEvent flows through the
 * TriggerDispatcher, the RuleEngine resolves matching rules and the
 * ActionExecutor runs the action chain.
 *
 * Uses `write_log_note` as the side-effect-bearing action so we can
 * assert without mocking Statamic facades.
 */
class RuleExecutesMultipleActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function fireTrigger(string $handle, array $payload, string $site = 'default'): void
    {
        $event = new TriggerEvent(
            triggerHandle: $handle,
            sourceType: 'test',
            sourceReference: '1',
            payload: $payload,
            site: $site,
        );

        /** @var TriggerDispatcher $dispatcher */
        $dispatcher = $this->app->make(TriggerDispatcher::class);
        $dispatcher->dispatch($event);
    }

    public function test_rule_with_matching_conditions_runs_all_actions_in_order(): void
    {
        Rule::create([
            'name' => 'Notify approved',
            'handle' => 'notify-approved',
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'conditions' => [
                'field' => 'data.status', 'op' => 'equals', 'value' => 'approved',
            ],
            'actions' => [
                ['handle' => 'write_log_note', 'config' => ['message' => 'step one']],
                ['handle' => 'write_log_note', 'config' => ['message' => 'step two']],
            ],
        ]);

        $this->fireTrigger('entry.published', ['data' => ['status' => 'approved']]);

        $notes = LogEntry::where('type', 'rule_log_note')->orderBy('id')->get();
        $this->assertCount(2, $notes);
        $this->assertSame('step one', $notes[0]->message);
        $this->assertSame('step two', $notes[1]->message);

        $this->assertSame(1, LogEntry::where('type', 'rule_executed')->count());
    }

    public function test_rule_with_failing_conditions_does_not_run_actions(): void
    {
        Rule::create([
            'name' => 'Notify approved',
            'handle' => 'notify-approved-2',
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'conditions' => [
                'field' => 'data.status', 'op' => 'equals', 'value' => 'approved',
            ],
            'actions' => [
                ['handle' => 'write_log_note', 'config' => ['message' => 'never']],
            ],
        ]);

        $this->fireTrigger('entry.published', ['data' => ['status' => 'draft']]);

        $this->assertSame(0, LogEntry::where('type', 'rule_log_note')->count());
        $this->assertSame(0, LogEntry::where('type', 'rule_executed')->count());
    }

    public function test_disabled_rule_does_not_run(): void
    {
        Rule::create([
            'name' => 'Disabled',
            'handle' => 'disabled-rule',
            'enabled' => false,
            'trigger_type' => 'entry.published',
            'actions' => [
                ['handle' => 'write_log_note', 'config' => ['message' => 'never']],
            ],
        ]);

        $this->fireTrigger('entry.published', []);

        $this->assertSame(0, LogEntry::where('type', 'rule_log_note')->count());
    }

    public function test_only_rules_for_the_actual_trigger_run(): void
    {
        Rule::create([
            'name' => 'Asset only',
            'handle' => 'asset-only',
            'enabled' => true,
            'trigger_type' => 'asset.saved',
            'actions' => [
                ['handle' => 'write_log_note', 'config' => ['message' => 'asset run']],
            ],
        ]);
        Rule::create([
            'name' => 'Entry only',
            'handle' => 'entry-only',
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'actions' => [
                ['handle' => 'write_log_note', 'config' => ['message' => 'entry run']],
            ],
        ]);

        $this->fireTrigger('entry.published', []);

        $notes = LogEntry::where('type', 'rule_log_note')->get();
        $this->assertCount(1, $notes);
        $this->assertSame('entry run', $notes[0]->message);
    }

    public function test_stop_on_failure_short_circuits_subsequent_actions(): void
    {
        Rule::create([
            'name' => 'Halt',
            'handle' => 'halt',
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'stop_on_failure' => true,
            'actions' => [
                ['handle' => 'unknown_handle', 'config' => []],
                ['handle' => 'write_log_note', 'config' => ['message' => 'should not run']],
            ],
        ]);

        $this->fireTrigger('entry.published', []);

        $this->assertSame(0, LogEntry::where('type', 'rule_log_note')->count());
        $this->assertSame(1, LogEntry::where('type', 'rule_executed')->count());
    }

    public function test_rules_run_in_order_index_order(): void
    {
        Rule::create([
            'name' => 'Second',
            'handle' => 'second',
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'order_index' => 10,
            'actions' => [['handle' => 'write_log_note', 'config' => ['message' => 'second']]],
        ]);
        Rule::create([
            'name' => 'First',
            'handle' => 'first',
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'order_index' => 1,
            'actions' => [['handle' => 'write_log_note', 'config' => ['message' => 'first']]],
        ]);

        $this->fireTrigger('entry.published', []);

        $notes = LogEntry::where('type', 'rule_log_note')->orderBy('id')->get();
        $this->assertCount(2, $notes);
        $this->assertSame('first', $notes[0]->message);
        $this->assertSame('second', $notes[1]->message);
    }
}
