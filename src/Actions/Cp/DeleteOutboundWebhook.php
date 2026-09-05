<?php

namespace Goldnead\WebhookManager\Actions\Cp;

use Goldnead\WebhookManager\Domain\OutboundWebhook\Actions\DeleteOutboundWebhookAction;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Statamic\Actions\Action;

/**
 * Delete outbound webhooks from a listing.
 *
 * `$dangerous` is what makes core render it in the destructive style and put a
 * confirmation in front of it, with the count in the sentence. That is the
 * reason to route deletion through an action instead of a `Button
 * variant="danger"`: the confirmation, the authorization check per item and the
 * toast all come from core and behave like everywhere else in the CP.
 */
class DeleteOutboundWebhook extends Action
{
    protected static $handle = 'webhook_manager_delete_outbound';

    protected $dangerous = true;

    public static function title()
    {
        return __('webhook-manager::messages.cp.bulk_delete');
    }

    public function icon(): string
    {
        return 'trash';
    }

    public function visibleTo($item)
    {
        return $item instanceof OutboundWebhook;
    }

    public function authorize($user, $item)
    {
        return (bool) $user?->can('manage outbound webhooks');
    }

    public function buttonText()
    {
        return trans_choice('webhook-manager::messages.cp.bulk_delete_button', $this->items->count());
    }

    public function confirmationText()
    {
        return trans_choice('webhook-manager::messages.cp.bulk_delete_confirm', $this->items->count());
    }

    public function run($items, $values)
    {
        $delete = app(DeleteOutboundWebhookAction::class);

        $items->each(fn (OutboundWebhook $hook) => $delete($hook));

        return trans_choice('webhook-manager::messages.cp.bulk_deleted', $items->count());
    }
}
