<?php

namespace Goldnead\WebhookManager\Jobs;

use Goldnead\WebhookManager\Domain\Delivery\Actions\PruneDeliveriesAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PruneWebhookDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $deliveryDays, public int $logDays)
    {
    }

    public function handle(PruneDeliveriesAction $prune): void
    {
        $prune($this->deliveryDays, $this->logDays);
    }
}
