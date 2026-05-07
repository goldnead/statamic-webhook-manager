<?php

namespace Goldnead\WebhookManager\Domain\Delivery\Actions;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;

class MarkDeliveryFailureAction
{
    public function __invoke(Delivery $delivery, string $errorType, string $message): Delivery
    {
        $delivery->status = Delivery::STATUS_FAILED;
        $delivery->error_type = $errorType;
        $delivery->error_message = $message;
        $delivery->save();

        return $delivery;
    }
}
