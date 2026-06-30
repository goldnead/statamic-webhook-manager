<?php

namespace Goldnead\WebhookManager\Domain\OutboundWebhook\Actions;

use Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;

class ToggleOutboundWebhookAction
{
    public function __construct(protected OutboundWebhookRepositoryInterface $repository)
    {
    }

    public function __invoke(OutboundWebhook $hook, ?bool $enabled = null): OutboundWebhook
    {
        $hook->enabled = $enabled ?? ! $hook->enabled;

        return $this->repository->save($hook);
    }
}
