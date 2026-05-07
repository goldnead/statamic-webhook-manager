<?php

namespace Goldnead\WebhookManager\Jobs;

use Goldnead\WebhookManager\Domain\Delivery\Actions\ReplayDeliveryAction;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReplayDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $deliveryId, public bool $reRender = false)
    {
    }

    public function handle(ReplayDeliveryAction $replay): void
    {
        $delivery = Delivery::find($this->deliveryId);
        if (! $delivery) {
            return;
        }
        $replay($delivery, $this->reRender);
    }
}
