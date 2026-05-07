<?php

namespace Goldnead\WebhookManager\Domain\OutboundWebhook\Actions;

use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;

class DeleteOutboundWebhookAction
{
    public function __invoke(OutboundWebhook $hook): bool
    {
        return (bool) $hook->delete();
    }
}
