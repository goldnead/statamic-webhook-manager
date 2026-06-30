<?php

namespace Goldnead\WebhookManager\Domain\Rule\Actions;

use Goldnead\WebhookManager\Contracts\Repositories\RuleRepositoryInterface;
use Goldnead\WebhookManager\Domain\Rule\Models\Rule;

class ToggleRuleAction
{
    public function __construct(protected RuleRepositoryInterface $repository)
    {
    }

    public function __invoke(Rule $rule, ?bool $enabled = null): Rule
    {
        $rule->enabled = $enabled ?? ! $rule->enabled;

        return $this->repository->save($rule);
    }
}
