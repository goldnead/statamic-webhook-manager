<?php

namespace Goldnead\WebhookManager\Domain\Rule\Actions;

use Goldnead\WebhookManager\Domain\Rule\Models\Rule;

class DeleteRuleAction
{
    public function __invoke(Rule $rule): void
    {
        $rule->delete();
    }
}
