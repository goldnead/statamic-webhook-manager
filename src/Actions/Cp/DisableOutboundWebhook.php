<?php

namespace Goldnead\WebhookManager\Actions\Cp;

use Goldnead\WebhookManager\Domain\OutboundWebhook\Actions\ToggleOutboundWebhookAction;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Statamic\Actions\Action;

/**
 * The counterpart to {@see EnableOutboundWebhook}. Split into two actions rather
 * than one "toggle", because core offers the bulk bar only the actions that are
 * visible for *every* checked row — a single toggle across a mixed selection
 * would have to guess a direction, and the operator would not see which one.
 */
class DisableOutboundWebhook extends Action
{
    protected static $handle = 'webhook_manager_disable_outbound';

    public static function title()
    {
        return __('webhook-manager::messages.cp.bulk_disable');
    }

    public function icon(): string
    {
        return 'fieldtype-toggle';
    }

    public function visibleTo($item)
    {
        return $item instanceof OutboundWebhook && (bool) $item->enabled;
    }

    public function authorize($user, $item)
    {
        return (bool) $user?->can('manage outbound webhooks');
    }

    public function buttonText()
    {
        return trans_choice('webhook-manager::messages.cp.bulk_disable_button', $this->items->count());
    }

    public function run($items, $values)
    {
        $toggle = app(ToggleOutboundWebhookAction::class);

        $items->each(fn (OutboundWebhook $hook) => $toggle($hook, false));

        return trans_choice('webhook-manager::messages.cp.bulk_disabled', $items->count());
    }
}
