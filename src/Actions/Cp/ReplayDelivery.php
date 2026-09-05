<?php

namespace Goldnead\WebhookManager\Actions\Cp;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Http\Controllers\Cp\Actions\ReplayDeliveryController;
use Goldnead\WebhookManager\Jobs\ReplayDeliveryJob;
use Statamic\Actions\Action;

/**
 * Replay failed deliveries from a listing.
 *
 * Same job the single-delivery replay button dispatches
 * ({@see ReplayDeliveryController}),
 * so there is one code path for "send this again" and not two that can drift.
 *
 * `visibleTo()` asks the model, not the listing: only a failed or cancelled
 * delivery is replayable, and a selection that mixes in a successful one must
 * not offer the action at all rather than silently skip half of it.
 */
class ReplayDelivery extends Action
{
    protected static $handle = 'webhook_manager_replay_delivery';

    public static function title()
    {
        return __('webhook-manager::messages.cp.bulk_replay');
    }

    public function icon(): string
    {
        // From core's 548-icon set — an icon name that does not exist there
        // renders nothing, silently (ui-vocabulary §9.1).
        return 'sync';
    }

    public function visibleTo($item)
    {
        return $item instanceof Delivery && $item->isReplayable();
    }

    public function authorize($user, $item)
    {
        return (bool) $user?->can('replay webhook deliveries');
    }

    public function buttonText()
    {
        return trans_choice('webhook-manager::messages.cp.bulk_replay_button', $this->items->count());
    }

    public function run($items, $values)
    {
        foreach ($items as $delivery) {
            ReplayDeliveryJob::dispatch($delivery->id, false)
                ->onConnection(config('webhook-manager.queue.connection'))
                ->onQueue(config('webhook-manager.queue.name', 'default'));
        }

        return trans_choice('webhook-manager::messages.cp.bulk_replayed', $items->count());
    }
}
