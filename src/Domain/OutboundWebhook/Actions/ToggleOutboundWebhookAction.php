<?php

namespace Goldnead\WebhookManager\Domain\OutboundWebhook\Actions;

use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;

class ToggleOutboundWebhookAction
{
    public function __invoke(OutboundWebhook $hook, ?bool $enabled = null): OutboundWebhook
    {
        $hook->enabled = $enabled ?? ! $hook->enabled;
        $hook->save();
        return $hook->fresh();
    }
}
