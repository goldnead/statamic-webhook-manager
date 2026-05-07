<?php

namespace Goldnead\WebhookManager\Console\Commands;

use Goldnead\WebhookManager\Domain\Delivery\Actions\PruneDeliveriesAction;
use Illuminate\Console\Command;

class PruneWebhookDataCommand extends Command
{
    protected $signature = 'webhook-manager:prune
        {--deliveries= : Override delivery prune age in days}
        {--logs= : Override log prune age in days}';

    protected $description = 'Prune old webhook deliveries and logs.';

    public function handle(PruneDeliveriesAction $prune): int
    {
        $deliveryDays = (int) ($this->option('deliveries') ?? config('webhook-manager.pruning.deliveries_after_days', 30));
        $logDays = (int) ($this->option('logs') ?? config('webhook-manager.pruning.logs_after_days', 60));

        $result = $prune($deliveryDays, $logDays);

        $this->info("Pruned {$result['deliveries']} deliveries and {$result['logs']} log entries.");

        return self::SUCCESS;
    }
}
