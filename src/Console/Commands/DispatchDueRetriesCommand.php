<?php

namespace Goldnead\WebhookManager\Console\Commands;

use Goldnead\BrandContext\Concerns\RunsForEachBrand;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Jobs\ProcessOutboundDeliveryJob;
use Goldnead\WebhookManager\Repositories\DeliveryRepository;
use Goldnead\WebhookManager\Services\DeliveryEngine;
use Illuminate\Console\Command;

/**
 * Runs the retries the RetryPlanner scheduled.
 *
 * This is the half of the retry story that was missing until now.
 * `DeliveryEngine` wrote `next_retry_at`, the CP rendered "next retry in 30
 * seconds", and `DeliveryRepository::readyForRetry()` — the query written
 * specifically to find those rows — had no caller anywhere in the package.
 * The result was a delivery that displayed as waiting for an attempt that
 * would never happen: the payload was gone and nothing said so.
 *
 * Registered on the scheduler by the service provider (every minute), so it
 * works on a stock Laravel install with only the usual `schedule:run` cron.
 * Also runnable by hand.
 *
 * **Claim-before-dispatch.** Each row's `next_retry_at` is cleared before the
 * attempt is handed off. Two overlapping scheduler runs, or a slow queue,
 * therefore cannot turn one planned attempt into two: the second run no longer
 * sees the row. If the attempt fails again, `DeliveryEngine` writes a fresh
 * `next_retry_at` and the row comes back on its own terms. If the process dies
 * between the claim and the dispatch, that one retry is lost rather than
 * duplicated — the safe direction for a webhook the receiver may not treat as
 * idempotent.
 */
class DispatchDueRetriesCommand extends Command
{
    use RunsForEachBrand;

    protected $signature = 'webhook-manager:dispatch-retries
        {--limit=200 : Maximum number of deliveries to pick up in one run}
        {--brand= : Restrict to one brand handle or id}';

    protected $description = 'Run the outbound deliveries whose scheduled retry is due.';

    public function handle(DeliveryRepository $deliveries, DeliveryEngine $engine): int
    {
        // A scheduled run has no session and therefore no brand; without this
        // the fail-closed scope hides every row and the command reports
        // success while doing nothing at all. Same reasoning as
        // ReplayFailedDeliveriesCommand.
        return $this->forEachBrand(fn () => $this->handleForBrand($deliveries, $engine));
    }

    protected function handleForBrand(DeliveryRepository $deliveries, DeliveryEngine $engine): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $due = $deliveries->readyForRetry()->take($limit);

        $dispatched = 0;

        foreach ($due as $delivery) {
            if (! $this->claim($delivery)) {
                continue;
            }

            $hook = $delivery->outboundWebhook;

            if ($hook && $hook->isQueueEnabled()) {
                ProcessOutboundDeliveryJob::dispatch($delivery->id)
                    ->onConnection(config('webhook-manager.queue.connection'))
                    ->onQueue(config('webhook-manager.queue.name', 'default'));
            } else {
                $engine->send($delivery);
            }

            $dispatched++;
        }

        $this->info("Dispatched {$dispatched} due ".str('retry')->plural($dispatched).'.');

        return self::SUCCESS;
    }

    /**
     * Take the row off the due list before working on it.
     *
     * The conditional update is the lock: whichever process updates the row
     * first gets a non-zero affected-row count and owns the attempt, the other
     * gets zero and skips.
     */
    protected function claim(Delivery $delivery): bool
    {
        $claimed = Delivery::query()
            ->whereKey($delivery->getKey())
            ->whereNotNull('next_retry_at')
            ->update(['next_retry_at' => null]);

        if ($claimed === 0) {
            return false;
        }

        $delivery->next_retry_at = null;

        return true;
    }
}
