<?php

namespace Goldnead\WebhookManager\Actions;

use Goldnead\WebhookManager\Contracts\ActionInterface;
use Goldnead\WebhookManager\Services\Logging\SystemLogger;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\ExecutionResult;

/**
 * Write a structured log note to the SystemLogger. Useful as a step in a
 * rule for staging behaviour ("only log for now") or as the final
 * confirmation in a longer rule chain.
 *
 * Rule config:
 *   - `message` (string, required)
 *   - `level` (string, optional, default `info`) — one of debug/info/warning/error
 *   - `type` (string, optional, default `rule_log_note`)
 */
class WriteLogNoteAction implements ActionInterface
{
    public function __construct(protected SystemLogger $logger)
    {
    }

    public function handle(): string
    {
        return 'write_log_note';
    }

    public function label(): string
    {
        return 'Write log note';
    }

    public function execute(array $config, ExecutionContext $context): ExecutionResult
    {
        $message = (string) ($config['message'] ?? '');
        if ($message === '') {
            return ExecutionResult::fail('Missing required config.message.');
        }

        $level = strtolower((string) ($config['level'] ?? 'info'));
        $level = in_array($level, ['debug', 'info', 'warning', 'error'], true) ? $level : 'info';
        $type = (string) ($config['type'] ?? 'rule_log_note');

        try {
            $entry = $this->logger->{$level}($type, $message, [
                'trigger' => $context->event->triggerHandle,
                'correlation_id' => $context->event->correlationId,
                'source_reference' => $context->event->sourceReference,
            ]);

            return ExecutionResult::ok('Log note written.', [
                'log_id' => $entry->id ?? null,
                'level' => $level,
            ]);
        } catch (\Throwable $e) {
            return ExecutionResult::fail('Failed to write log note: '.$e->getMessage());
        }
    }
}
