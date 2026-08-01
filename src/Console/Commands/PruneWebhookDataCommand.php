<?php

namespace Goldnead\WebhookManager\Console\Commands;

use Goldnead\BrandContext\Concerns\RunsForEachBrand;
use Goldnead\WebhookManager\Domain\Delivery\Actions\PruneDeliveriesAction;
use Illuminate\Console\Command;

class PruneWebhookDataCommand extends Command
{
    use RunsForEachBrand;

    protected $signature = 'webhook-manager:prune
        {--deliveries= : Override delivery prune age in days}
        {--logs= : Override log prune age in days} {--brand= : Restrict to one brand handle or id}';

    protected $description = 'Prune old webhook deliveries and logs.';

    public function handle(PruneDeliveriesAction $prune): int
    {
        // A scheduled run has no session and therefore no brand; without
        // this the fail-closed scope hides every row and the command
        // reports success while doing nothing at all.
        return $this->forEachBrand(fn () => $this->handleForBrand($prune));
    }

    protected function handleForBrand(PruneDeliveriesAction $prune): int
    {
        $deliveryDays = (int) ($this->option('deliveries') ?? config('webhook-manager.pruning.deliveries_after_days', 30));
        $logDays = (int) ($this->option('logs') ?? config('webhook-manager.pruning.logs_after_days', 60));

        $result = $prune($deliveryDays, $logDays);

        $this->info("Pruned {$result['deliveries']} deliveries and {$result['logs']} log entries.");

        return self::SUCCESS;
    }
}
