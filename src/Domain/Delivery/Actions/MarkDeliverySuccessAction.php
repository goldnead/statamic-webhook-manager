<?php

namespace Goldnead\WebhookManager\Domain\Delivery\Actions;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;

class MarkDeliverySuccessAction
{
    public function __invoke(Delivery $delivery): Delivery
    {
        $delivery->status = Delivery::STATUS_SUCCESS;
        $delivery->error_type = null;
        $delivery->error_message = null;
        $delivery->next_retry_at = null;
        $delivery->save();

        return $delivery;
    }
}
