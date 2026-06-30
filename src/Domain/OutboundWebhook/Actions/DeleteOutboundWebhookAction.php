<?php

namespace Goldnead\WebhookManager\Domain\OutboundWebhook\Actions;

use Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;

class DeleteOutboundWebhookAction
{
    public function __construct(protected OutboundWebhookRepositoryInterface $repository)
    {
    }

    public function __invoke(OutboundWebhook $hook): bool
    {
        return $this->repository->delete($hook);
    }
}
