<?php

namespace Goldnead\WebhookManager\Rules;

use Goldnead\WebhookManager\Registries\ActionRegistry;
use Goldnead\WebhookManager\Services\FailureClassifier;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\ExecutionResult;

/**
 * Execute an ordered list of action handles in an ExecutionContext.
 *
 * Catches handler throws, classifies them via `FailureClassifier`, and
 * surfaces the classification on the failed `ExecutionResult.data`
 * under the `error_type` key. This keeps the engine's caller (the
 * RuleEngine, the CP test panel, the future rule-actions queue job)
 * out of the per-exception-class business of every individual handler.
 */
class ActionExecutor
{
    public function __construct(
        protected ActionRegistry $registry,
        protected FailureClassifier $classifier,
    ) {
    }

    /**
     * @param  array<int, array{handle:string, config?:array}>  $actions
     * @return array<int, ExecutionResult>
     */
    public function run(array $actions, ExecutionContext $context, bool $stopOnFailure = false): array
    {
        $results = [];
        foreach ($actions as $action) {
            $handle = (string) ($action['handle'] ?? '');
            $impl = $this->registry->get($handle);

            if (! $impl) {
                $results[] = ExecutionResult::fail("Unknown action: {$handle}", [
                    'handle' => $handle,
                    'error_type' => FailureClassifier::CONFIGURATION,
                ]);
                if ($stopOnFailure) {
                    break;
                }
                continue;
            }

            try {
                $result = $impl->execute($action['config'] ?? [], $context);
                if (! $result->ok && ! array_key_exists('error_type', $result->data)) {
                    // Handler returned a clean fail() but didn't tag the
                    // type. Default to PAYLOAD — handlers reach this path
                    // when their config is missing required fields.
                    $result = new ExecutionResult($result->ok, $result->message, array_merge(
                        $result->data,
                        ['handle' => $handle, 'error_type' => FailureClassifier::PAYLOAD],
                    ));
                } elseif (! array_key_exists('handle', $result->data)) {
                    $result = new ExecutionResult($result->ok, $result->message, array_merge(
                        $result->data,
                        ['handle' => $handle],
                    ));
                }
            } catch (\Throwable $e) {
                $result = ExecutionResult::fail($e->getMessage(), [
                    'handle' => $handle,
                    'error_type' => $this->classifier->classifyException($e),
                    'exception' => get_class($e),
                ]);
            }

            $results[] = $result;
            if ($stopOnFailure && ! $result->ok) {
                break;
            }
        }
        return $results;
    }
}
