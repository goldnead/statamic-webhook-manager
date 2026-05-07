<?php

namespace Goldnead\WebhookManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * TODO: REVIEW — placeholder for the rule engine iteration.
 * Will dispatch a queued ActionExecutor::run for a given context.
 */
class DispatchRuleActionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $ruleId, public array $contextPayload)
    {
    }

    public function handle(): void
    {
        // Intentionally empty until the rule engine ships.
    }
}
