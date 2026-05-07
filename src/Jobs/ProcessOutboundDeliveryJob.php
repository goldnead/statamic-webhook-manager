<?php

namespace Goldnead\WebhookManager\Jobs;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Services\DeliveryEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends a Delivery from the queue.
 *
 * Idempotency: each Delivery is created BEFORE this job is dispatched and
 * carries its own status. Re-running the job (Laravel-level retry) is safe
 * because DeliveryEngine guards against double-success and increments
 * `attempts` on every send.
 *
 * Note: we do NOT rely on Laravel's queue retry for fachliches Retry — the
 * RetryPlanner schedules the next attempt via `next_retry_at` and a
 * scheduled job dispatcher (or the replay command) picks it up.
 */
class ProcessOutboundDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // we manage retries ourselves

    public function __construct(public int $deliveryId)
    {
    }

    public function handle(DeliveryEngine $engine): void
    {
        $delivery = Delivery::find($this->deliveryId);
        if (! $delivery) {
            return;
        }
        // Hard idempotency check: don't re-run a successful delivery.
        if ($delivery->status === Delivery::STATUS_SUCCESS) {
            return;
        }
        $engine->send($delivery);
    }
}
