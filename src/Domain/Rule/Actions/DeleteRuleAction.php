<?php

namespace Goldnead\WebhookManager\Domain\Rule\Actions;

use Goldnead\WebhookManager\Contracts\Repositories\RuleRepositoryInterface;
use Goldnead\WebhookManager\Domain\Rule\Models\Rule;

class DeleteRuleAction
{
    public function __construct(protected RuleRepositoryInterface $repository)
    {
    }

    public function __invoke(Rule $rule): void
    {
        $this->repository->delete($rule);
    }
}
