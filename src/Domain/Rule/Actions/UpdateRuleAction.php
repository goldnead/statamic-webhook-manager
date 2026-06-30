<?php

namespace Goldnead\WebhookManager\Domain\Rule\Actions;

use Goldnead\WebhookManager\Contracts\Repositories\RuleRepositoryInterface;
use Goldnead\WebhookManager\Domain\Rule\Models\Rule;

class UpdateRuleAction
{
    public function __construct(protected RuleRepositoryInterface $repository)
    {
    }

    public function __invoke(Rule $rule, array $attributes): Rule
    {
        $rule->fill($attributes);

        return $this->repository->save($rule);
    }
}
