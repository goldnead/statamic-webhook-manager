<?php

namespace Goldnead\WebhookManager\Contracts;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;

interface DeliverySenderInterface
{
    /**
     * Send the given delivery snapshot, update its status fields and
     * return the (refreshed) delivery model.
     */
    public function send(Delivery $delivery): Delivery;
}
