<?php

namespace Goldnead\WebhookManager\Services;

use Goldnead\WebhookManager\Contracts\DeliverySenderInterface;
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
    ) {
    }

    public function send(Delivery $delivery): Delivery
    {
        $hook = $delivery->outboundWebhook;
        if (! $hook) {
            return ($this->markFailure)($delivery, FailureClassifier::CONFIGURATION, 'Outbound webhook missing.');
        }

        $delivery->status = Delivery::STATUS_PROCESSING;
        $delivery->attempts = (int) $delivery->attempts + 1;
        $delivery->first_attempted_at = $delivery->first_attempted_at ?? now();
        $delivery->last_attempted_at = now();
        $delivery->save();

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

    protected function resolveEvaluator(?array $matcher): \Goldnead\WebhookManager\Contracts\SuccessEvaluatorInterface
    {
        if (! $matcher) {
            return $this->evaluators->default();
        }
        $handle = (string) ($matcher['evaluator'] ?? 'default');
        return $this->evaluators->get($handle) ?? $this->evaluators->default();
    }
}
