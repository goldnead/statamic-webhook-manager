<?php

namespace Goldnead\WebhookManager\Domain\Rule\Actions;

use Goldnead\WebhookManager\Domain\Rule\Models\Rule;

class UpdateRuleAction
{
    public function __invoke(Rule $rule, array $attributes): Rule
    {
        $rule->fill($attributes);
        $rule->save();

        return $rule->fresh();
    }
}
