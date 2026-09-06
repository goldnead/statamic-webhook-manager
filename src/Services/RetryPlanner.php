<?php

namespace Goldnead\WebhookManager\Services;

use Carbon\CarbonImmutable;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;

/**
 * Decides whether and when a failed delivery should be retried.
 *
 * Strategies:
 *  - none         — never retry
 *  - linear       — fixed `base_delay_seconds` per attempt up to `max_delay_seconds`
 *  - exponential  — base * 2 ** (attempt - 1), capped at `max_delay_seconds`
 *
 * Status-based filtering: only retry on configured codes / network errors.
 */
class RetryPlanner
{
    public function __construct(protected FailureClassifier $classifier) {}

    /**
     * @return ?CarbonImmutable next attempt time, or null if no retry.
     */
    public function plan(Delivery $delivery, OutboundWebhook $hook, array $response): ?CarbonImmutable
    {
        $strategy = $hook->retry_strategy ?? config('webhook-manager.retry');

        $type = $strategy['strategy'] ?? config('webhook-manager.retry.strategy', 'exponential');
        $maxAttempts = (int) ($strategy['max_attempts'] ?? config('webhook-manager.retry.max_attempts', 3));
        $base = (int) ($strategy['base_delay_seconds'] ?? config('webhook-manager.retry.base_delay_seconds', 30));
        $cap = (int) ($strategy['max_delay_seconds'] ?? config('webhook-manager.retry.max_delay_seconds', 3600));
        // `intval` on the way in, because the comparison below is strict and
        // `"429"` never equals `429`. The list reaches here from three places
        // that can all hand back strings: a host-edited
        // `config/webhook-manager.php`, the hook's own `retry_strategy` JSON,
        // and — since the settings screen moved to the suite's shared layer —
        // the settings form, whose list control has no per-item type and
        // stores every line as a string. Without this cast a saved status list
        // silently stops matching anything and nothing is ever retried.
        $allowedStatus = array_map(
            'intval',
            (array) ($strategy['retry_on_status'] ?? config('webhook-manager.retry.retry_on_status', [500, 502, 503, 504]))
        );
        $retryNetwork = (bool) ($strategy['retry_on_network_errors'] ?? config('webhook-manager.retry.retry_on_network_errors', true));

        if ($type === 'none' || $maxAttempts <= 0) {
            return null;
        }
        if ($delivery->attempts >= $maxAttempts) {
            return null;
        }

        $errorType = $this->classifier->classify($response);

        $isNetwork = in_array($errorType, [FailureClassifier::NETWORK, FailureClassifier::TIMEOUT, FailureClassifier::INTERNAL], true);
        if ($isNetwork && ! $retryNetwork) {
            return null;
        }
        if (! $isNetwork) {
            $status = (int) ($response['status'] ?? 0);
            if ($status === 0 || ! in_array($status, $allowedStatus, true)) {
                return null;
            }
        }

        $delay = match ($type) {
            'linear' => $base * max(1, $delivery->attempts),
            default => $base * (int) (2 ** max(0, $delivery->attempts - 1)),
        };
        $delay = min($delay, $cap);

        return CarbonImmutable::now()->addSeconds($delay);
    }
}
