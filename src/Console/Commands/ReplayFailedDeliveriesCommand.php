<?php

namespace Goldnead\WebhookManager\Console\Commands;

use Carbon\Carbon;
use Goldnead\WebhookManager\Services\DeliveryReplayService;
use Illuminate\Console\Command;

class ReplayFailedDeliveriesCommand extends Command
{
    protected $signature = 'webhook-manager:replay-failed
        {--hours=24 : Look back this many hours}
        {--re-render : Re-render the request body using current data}';

    protected $description = 'Bulk replay failed deliveries from the last N hours.';

    public function handle(DeliveryReplayService $service): int
    {
        $hours = (int) $this->option('hours');
        $reRender = (bool) $this->option('re-render');
        $since = Carbon::now()->subHours($hours);

        $count = $service->replayFailedSince($since, $reRender);
        $this->info("Replayed {$count} deliveries from the last {$hours}h.");

        return self::SUCCESS;
    }
}
