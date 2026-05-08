<?php

namespace Goldnead\WebhookManager\Domain\Rule\Actions;

use Goldnead\WebhookManager\Domain\Rule\Models\Rule;

class ToggleRuleAction
{
    public function __invoke(Rule $rule, ?bool $enabled = null): Rule
    {
        $rule->enabled = $enabled ?? ! $rule->enabled;
        $rule->save();
        return $rule->fresh();
    }
}
