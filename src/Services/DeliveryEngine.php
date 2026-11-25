<?php

namespace Goldnead\WebhookManager\Services;

use Goldnead\WebhookManager\Contracts\DeliverySenderInterface;
use Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface;
use Goldnead\WebhookManager\Contracts\SuccessEvaluatorInterface;
use Goldnead\WebhookManager\Domain\Delivery\Actions\MarkDeliveryFailureAction;
use Goldnead\WebhookManager\Domain\Delivery\Actions\MarkDeliverySuccessAction;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Events\DeliveryFailedTerminally;
use Goldnead\WebhookManager\Registries\SuccessEvaluatorRegistry;
use Goldnead\WebhookManager\Services\Http\HttpClient;
use Goldnead\WebhookManager\Services\Http\HttpResponseNormalizer;
use Goldnead\WebhookManager\Services\Logging\DeliveryLogger;

/**
 * Sends a Delivery on the wire.
 *
 * Steps:
 *  1. Mark processing, increment attempts
 *  2. Issue HTTP request via the wrapped client
 *  3. Apply response to the snapshot via the normalizer
 *  4. Decide success vs failure (success evaluator + classifier)
 *  5. Plan retry if applicable, persist all changes, log outcome
 */
class DeliveryEngine implements DeliverySenderInterface
{
    public function __construct(
        protected HttpClient $http,
        protected HttpResponseNormalizer $normalizer,
        protected FailureClassifier $classifier,
        protected RetryPlanner $retry,
        protected SuccessEvaluatorRegistry $evaluators,
        protected MarkDeliverySuccessAction $markSuccess,
        protected MarkDeliveryFailureAction $markFailure,
        protected DeliveryLogger $logger,
        protected CircuitBreaker $circuitBreaker,
        protected OutboundWebhookRepositoryInterface $webhooks,
    ) {}

    public function send(Delivery $delivery): Delivery
    {
        $hook = $this->webhooks->find($delivery->outbound_webhook_id);
        if (! $hook) {
            return ($this->markFailure)($delivery, FailureClassifier::CONFIGURATION, 'Outbound webhook missing.');
        }

        $delivery->status = Delivery::STATUS_PROCESSING;
        $delivery->attempts = (int) $delivery->attempts + 1;
        $delivery->first_attempted_at = $delivery->first_attempted_at ?? now();
        $delivery->last_attempted_at = now();
        $delivery->save();

        try {
            return $this->dispatchRequest($delivery, $hook);
        } catch (\Throwable $e) {
            // The row is on `processing` and has no `next_retry_at`: nothing would
            // ever pick it up again. The HTTP call may already have gone out, so
            // no automatic retry (it could double-deliver) — close it as failed
            // and alert.
            report($e);

            return $this->failInternally($delivery, $e->getMessage());
        }
    }

    /**
     * Close a delivery that is stuck on `processing` because something other
     * than the HTTP exchange broke. Does not trip the circuit breaker: the
     * receiving system is not at fault.
     */
    public function failInternally(Delivery $delivery, string $message): Delivery
    {
        // Atomic and only from `processing`: a delivery that already finished
        // (success, or failed with its real cause and retry plan) must keep its
        // state, and a late `failed()` must not race a worker that is still
        // writing the result.
        $closed = Delivery::query()
            ->whereKey($delivery->getKey())
            ->where('status', Delivery::STATUS_PROCESSING)
            ->update([
                'status' => Delivery::STATUS_FAILED,
                'error_type' => FailureClassifier::INTERNAL,
                'error_message' => $message,
                'next_retry_at' => null,
            ]);

        $delivery->refresh();

        if ($closed !== 1) {
            return $delivery;
        }

        // Alert first: a broken logger must not swallow it.
        try {
            DeliveryFailedTerminally::dispatch($delivery);
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            $this->logger->failed($delivery, $message);
        } catch (\Throwable $e) {
            report($e);
        }

        return $delivery;
    }

    protected function dispatchRequest(Delivery $delivery, $hook): Delivery
    {
        $request = [
            'method' => $delivery->request_method,
            'url' => $delivery->request_url,
            'headers' => (array) $delivery->request_headers,
            'body' => $delivery->request_body ?? '',
        ];

        $response = $this->http->send(
            $request,
            (int) ($hook->timeout_seconds ?? config('webhook-manager.http.timeout_seconds', 15)),
            (bool) ($hook->follow_redirects ?? true),
        );

        $logMode = $hook->log_body_mode ?? config('webhook-manager.logging.mode', 'partial');
        $this->normalizer->applyToDelivery($delivery, $response, $logMode);

        $evaluator = $this->resolveEvaluator($hook->success_matcher ?? null);
        $isSuccess = ($response['ok'] ?? false) && $evaluator->isSuccess($response, (array) ($hook->success_matcher ?? []));

        if ($isSuccess) {
            $this->circuitBreaker->recordSuccess($hook);
            $this->logger->success($delivery);

            return ($this->markSuccess)($delivery);
        }

        $errorType = $this->classifier->classify($response);
        $message = $response['error_message']
            ?? ($response['status'] ? "HTTP {$response['status']}" : 'Unknown failure');

        $delivery = ($this->markFailure)($delivery, $errorType, $message);
        $this->logger->failed($delivery, $message);

        $nextRetry = $this->retry->plan($delivery, $hook, $response);
        if ($nextRetry) {
            $delivery->next_retry_at = $nextRetry;
            $delivery->save();
        } else {
            // No more retries — failed for good. Trip the circuit breaker and alert.
            $this->circuitBreaker->recordFailure($hook);
            DeliveryFailedTerminally::dispatch($delivery);
        }

        return $delivery;
    }

    protected function resolveEvaluator(?array $matcher): SuccessEvaluatorInterface
    {
        if (! $matcher) {
            return $this->evaluators->default();
        }
        $handle = (string) ($matcher['evaluator'] ?? 'default');

        return $this->evaluators->get($handle) ?? $this->evaluators->default();
    }
}
