<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Statamic\Http\Controllers\CP\ActionController;

/**
 * Row and bulk actions for delivery listings — see
 * {@see OutboundActionController} for what the two endpoints are.
 *
 * The lookup deliberately goes through the model's default query rather than
 * around it: the brand scope on `Delivery` is what keeps an operator from
 * replaying another brand's delivery by posting its id.
 */
class DeliveryActionController extends ActionController
{
    protected static $key = 'webhook-manager.deliveries';

    protected function getSelectedItems($items, $context)
    {
        return Delivery::query()
            ->whereIn((new Delivery)->getQualifiedKeyName(), $items->all())
            ->get();
    }
}
