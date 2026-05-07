<?php

namespace Goldnead\WebhookManager\Repositories;

use Goldnead\WebhookManager\Domain\Rule\Models\Rule;
use Illuminate\Support\Collection;

/**
 * TODO: REVIEW — engine is a no-op; this repository will gain real queries
 * once the rule engine is implemented.
 */
class RuleRepository
{
    public function activeForTrigger(string $triggerHandle): Collection
    {
        return Rule::where('enabled', true)
            ->where('trigger_type', $triggerHandle)
            ->orderBy('order_index')
            ->get();
    }

    public function all(): Collection
    {
        return Rule::orderBy('name')->get();
    }
}
