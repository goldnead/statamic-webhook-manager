<?php

namespace Goldnead\WebhookManager\Rules;

use Goldnead\WebhookManager\Registries\ActionRegistry;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\ExecutionResult;

/**
 * TODO: REVIEW — used by the future rule engine. For now, the dispatcher
 * runs deliveries directly without going through the action layer.
 */
class ActionExecutor
{
    public function __construct(protected ActionRegistry $registry)
    {
    }

    /**
     * @param  array<int, array{handle:string, config?:array}>  $actions
     * @return array<int, ExecutionResult>
     */
    public function run(array $actions, ExecutionContext $context, bool $stopOnFailure = false): array
    {
        $results = [];
        foreach ($actions as $action) {
            $impl = $this->registry->get($action['handle'] ?? '');
            if (! $impl) {
                $results[] = ExecutionResult::fail("Unknown action: {$action['handle']}");
                if ($stopOnFailure) {
                    break;
                }
                continue;
            }
            try {
                $result = $impl->execute($action['config'] ?? [], $context);
            } catch (\Throwable $e) {
                $result = ExecutionResult::fail($e->getMessage());
            }
            $results[] = $result;
            if ($stopOnFailure && ! $result->ok) {
                break;
            }
        }
        return $results;
    }
}
