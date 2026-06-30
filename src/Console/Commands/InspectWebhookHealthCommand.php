<?php

namespace Goldnead\WebhookManager\Console\Commands;

use Goldnead\WebhookManager\Repositories\DeliveryRepository;
use Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface;
use Illuminate\Console\Command;

class InspectWebhookHealthCommand extends Command
{
    protected $signature = 'webhook-manager:health';
    protected $description = 'Show counts and a quick health snapshot.';

    public function handle(
        OutboundWebhookRepositoryInterface $hooks,
        DeliveryRepository $deliveries,
    ): int {
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
