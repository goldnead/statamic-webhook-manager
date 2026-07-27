<?php

namespace Goldnead\WebhookManager\Console\Commands;

use Goldnead\WebhookManager\Repositories\DeliveryRepository;
use Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface;
use Goldnead\BrandContext\Concerns\RunsForEachBrand;
use Illuminate\Console\Command;

class InspectWebhookHealthCommand extends Command
{
    use RunsForEachBrand;

    protected $signature = 'webhook-manager:health {--brand= : Restrict to one brand handle or id}';
    protected $description = 'Show counts and a quick health snapshot.';

    public function handle(
        OutboundWebhookRepositoryInterface $hooks,
        DeliveryRepository $deliveries,
    ): int
    {
        // A scheduled run has no session and therefore no brand; without
        // this the fail-closed scope hides every row and the command
        // reports success while doing nothing at all.
        return $this->forEachBrand(fn () => $this->handleForBrand($hooks, $deliveries));
    }

    protected function handleForBrand(
        OutboundWebhookRepositoryInterface $hooks,
        DeliveryRepository $deliveries,
    ): int
    {
        $counts = $deliveries->counts();
        $rate = $deliveries->successRate(24);

        $this->info('Statamic Webhook Manager — health');
        $this->table(['Metric', 'Value'], [
            ['Active outbound hooks', $hooks->countActive()],
            ['Deliveries (success)', $counts['success']],
            ['Deliveries (failed)', $counts['failed']],
            ['Deliveries (pending/processing)', $counts['pending']],
            ['Success rate 24h', $rate.'%'],
        ]);

        return self::SUCCESS;
    }
}
