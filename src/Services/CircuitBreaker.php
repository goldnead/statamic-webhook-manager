<?php

namespace Goldnead\WebhookManager\Services;

use Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Services\Logging\SystemLogger;

/**
 * Tracks consecutive terminal failures per outbound webhook and auto-disables
 * a hook once it crosses the configured threshold, so a dead endpoint stops
 * being hammered. The counter resets on the next success.
 */
class CircuitBreaker
{
    public function __construct(
        protected SystemLogger $logger,
        protected OutboundWebhookRepositoryInterface $repository,
    ) {
    }

    public function recordSuccess(OutboundWebhook $hook): void
    {
        if ((int) $hook->consecutive_failures !== 0) {
            $hook->consecutive_failures = 0;
            $this->repository->save($hook);
        }
    }

    /**
     * Increment the failure counter and disable the hook if it trips the
     * breaker. Returns true if the hook was just auto-disabled.
     */
    public function recordFailure(OutboundWebhook $hook): bool
    {
        $hook->consecutive_failures = (int) $hook->consecutive_failures + 1;

        $tripped = false;
        if (config('webhook-manager.circuit_breaker.enabled', true)) {
            $threshold = (int) config('webhook-manager.circuit_breaker.threshold', 10);

            if ($threshold > 0 && $hook->consecutive_failures >= $threshold && $hook->enabled) {
                $hook->enabled = false;
                $tripped = true;

                $this->logger->warning(
                    'circuit_breaker_tripped',
                    "Outbound webhook '{$hook->handle}' auto-disabled after {$hook->consecutive_failures} consecutive failures.",
                    ['outbound_webhook_id' => $hook->id, 'handle' => $hook->handle],
                );
            }
        }

        $this->repository->save($hook);

        return $tripped;
    }
}
