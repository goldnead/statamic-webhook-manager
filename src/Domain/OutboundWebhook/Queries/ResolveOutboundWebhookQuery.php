<?php

namespace Goldnead\WebhookManager\Domain\OutboundWebhook\Queries;

use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface;
use Goldnead\WebhookManager\Rules\ConditionEvaluator;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Illuminate\Support\Collection;

/**
 * Resolves outbound webhooks that should fire for a given execution
 * context — matches by trigger handle, then evaluates each hook's
 * `conditions` config.
 */
class ResolveOutboundWebhookQuery
{
    public function __construct(
        protected OutboundWebhookRepositoryInterface $repository,
        protected ConditionEvaluator $conditions,
    ) {
    }

    /**
     * @return Collection<int, OutboundWebhook>
     */
    public function __invoke(ExecutionContext $context): Collection
    {
        $hooks = $this->repository->activeForTrigger($context->event->triggerHandle);

        return $hooks->filter(fn (OutboundWebhook $hook) => $this->conditions->evaluate(
            $hook->conditions ?? [],
            $context,
        ))->values();
    }
}
