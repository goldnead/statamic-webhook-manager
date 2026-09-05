<?php

namespace Goldnead\WebhookManager\Actions\Cp;

use Goldnead\WebhookManager\Domain\OutboundWebhook\Actions\ToggleOutboundWebhookAction;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Statamic\Actions\Action;

/**
 * Switch outbound webhooks on, from the "…" menu of a row or from the bulk bar
 * once several rows are checked.
 *
 * A native Statamic action rather than another hand-rolled button, because that
 * is the only mechanism core's <Listing> can offer as a *multi*-selection: the
 * checkbox column only does something when an action endpoint answers behind
 * `actionUrl`. Before this class the listing showed checkboxes whose bulk menu
 * was empty.
 *
 * `visibleTo()` is what keeps this out of every other listing in the Control
 * Panel — actions live in one global registry and core asks every one of them
 * about every item.
 */
class EnableOutboundWebhook extends Action
{
    /**
     * Explicit, because the registry is keyed by handle across all addons and a
     * derived `enable_outbound_webhook` is a name a sibling could pick too.
     */
    protected static $handle = 'webhook_manager_enable_outbound';

    public static function title()
    {
        return __('webhook-manager::messages.cp.bulk_enable');
    }

    public function icon(): string
    {
        return 'fieldtype-toggle';
    }

    public function visibleTo($item)
    {
        return $item instanceof OutboundWebhook && ! $item->enabled;
    }

    public function authorize($user, $item)
    {
        return (bool) $user?->can('manage outbound webhooks');
    }

    public function buttonText()
    {
        return trans_choice('webhook-manager::messages.cp.bulk_enable_button', $this->items->count());
    }

    public function run($items, $values)
    {
        $toggle = app(ToggleOutboundWebhookAction::class);

        $items->each(fn (OutboundWebhook $hook) => $toggle($hook, true));

        return trans_choice('webhook-manager::messages.cp.bulk_enabled', $items->count());
    }
}
