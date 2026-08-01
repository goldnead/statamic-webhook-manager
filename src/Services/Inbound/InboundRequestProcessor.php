<?php

namespace Goldnead\WebhookManager\Services\Inbound;

use Goldnead\WebhookManager\Auth\Support\ReplayProtectionService;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Goldnead\WebhookManager\Services\Logging\SystemLogger;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Orchestrates the inbound pipeline:
 *
 *   1. rate limit                   → 429 on failure
 *   2. allowed-method check         → 405 on failure
 *   3. payload size check           → 413 on failure
 *   4. auth verification            → 401 on failure
 *   5. content-type / parsing       → 400 on failure
 *   6. replay protection (optional) → 409 on failure
 *   7. mapping engine               → 422 on failure
 *   8. action dispatch              → 422/200 from action result
 *   9. response builder             → final JSON response
 *
 * Each failure short-circuits the pipeline and writes a structured
 * SystemLogger entry so failed requests are reviewable in the CP without
 * having to scrape webserver logs.
 *
 * The rate limit runs first, and here rather than as route middleware, for
 * three reasons: it is the cheapest guard, so nothing after it can be used to
 * burn resources; it needs the endpoint to honour a per-endpoint override; and
 * every route that reaches this class — canonical prefix and the legacy `!/`
 * alias alike — is throttled by the same counter, with no way to configure one
 * of them out of the stack.
 */
class InboundRequestProcessor
{
    public function __construct(
        protected InboundAuthVerifier $auth,
        protected InboundPayloadParser $parser,
        protected InboundMappingService $mapping,
        protected InboundActionDispatcher $dispatcher,
        protected InboundResponseBuilder $responder,
        protected ReplayProtectionService $replay,
        protected SystemLogger $logger,
        protected RateLimiter $limiter,
    ) {
    }

    public function process(Request $request, InboundEndpoint $endpoint): JsonResponse
    {
        $correlationId = (string) \Illuminate\Support\Str::uuid();
        $logCtx = [
            'endpoint_id' => $endpoint->id,
            'handle' => $endpoint->handle,
            'correlation_id' => $correlationId,
        ];

        // 1. Rate limit
        $limit = $this->rateLimitFor($endpoint);
        if ($limit > 0) {
            $key = $this->rateLimitKey($endpoint);

            if ($this->limiter->tooManyAttempts($key, $limit)) {
                $retryAfter = $this->limiter->availableIn($key);

                $this->logger->warning('inbound_rate_limited',
                    "Rate limit of {$limit}/min exceeded on {$endpoint->handle}",
                    $logCtx + ['limit_per_minute' => $limit, 'retry_after_seconds' => $retryAfter]);

                return $this->error($endpoint, 'Too many requests.', 429)
                    ->withHeaders($this->rateLimitHeaders($limit, 0) + ['Retry-After' => (string) $retryAfter]);
            }

            $this->limiter->hit($key, 60);

            $remaining = max(0, $limit - $this->limiter->attempts($key));

            // Every response out of this pipeline carries the quota, not just
            // the rejection — a sender can back off before it gets a 429.
            return $this->runPipeline($request, $endpoint, $logCtx)
                ->withHeaders($this->rateLimitHeaders($limit, $remaining));
        }

        return $this->runPipeline($request, $endpoint, $logCtx);
    }

    /**
     * Steps 2–9. Split out so the rate limiter can decorate whatever comes
     * back without every early return having to remember the headers.
     *
     * @param  array<string, mixed>  $logCtx
     */
    protected function runPipeline(Request $request, InboundEndpoint $endpoint, array $logCtx): JsonResponse
    {
        // 2. Method allowlist
        $allowed = array_map('strtoupper', (array) ($endpoint->allowed_methods ?? ['POST']));
        if ($allowed && ! in_array(strtoupper($request->method()), $allowed, true)) {
            $this->logger->warning('inbound_method_not_allowed',
                "Method {$request->method()} not allowed on {$endpoint->handle}", $logCtx);
            return $this->error($endpoint, 'Method not allowed.', 405);
        }

        // 3. Payload size
        $maxKb = (int) ($endpoint->max_payload_kb ?? config('webhook-manager.inbound.max_payload_kb', 512));
        if ($maxKb > 0) {
            $bodyLen = strlen((string) $request->getContent());
            if ($bodyLen > $maxKb * 1024) {
                $this->logger->warning('inbound_payload_too_large',
                    "Payload {$bodyLen}B exceeds {$maxKb}KB on {$endpoint->handle}", $logCtx);
                return $this->error($endpoint, 'Payload too large.', 413);
            }
        }

        // 4. Auth
        if (! $this->auth->verify($request, $endpoint)) {
            $this->logger->warning('inbound_auth_failed',
                "Auth failed on {$endpoint->handle} ({$endpoint->auth_type})", $logCtx);
            return $this->error($endpoint, 'Unauthorized.', 401);
        }

        // 5. Parse
        $contentType = (string) ($endpoint->expected_content_type ?? 'application/json');
        $parsed = $this->parser->parse($request, $contentType);
        if (! $parsed['ok']) {
            $this->logger->warning('inbound_parse_failed', $parsed['error'] ?? 'Parse failed', $logCtx);
            return $this->error($endpoint, $parsed['error'] ?? 'Bad request.', 400);
        }
        $rawPayload = $parsed['data'];

        // 6. Replay protection (optional)
        if ($endpoint->replay_protection_enabled) {
            $key = $this->replayKey($request, $rawPayload, $endpoint);
            if (! $this->replay->check($key)) {
                $this->logger->warning('inbound_replay_blocked',
                    "Replay-protected request rejected on {$endpoint->handle}", $logCtx);
                return $this->error($endpoint, 'Duplicate request.', 409);
            }
        }

        // 7. Mapping
        $mapped = $this->mapping->map($endpoint->mapping_config ?? null, $rawPayload);
        if (! $mapped['ok']) {
            $this->logger->warning('inbound_mapping_failed',
                'Mapping errors: '.implode('; ', $mapped['errors'] ?? []), $logCtx);
            return $this->error($endpoint, 'Mapping failed.', 422, [
                'errors' => $mapped['errors'] ?? [],
            ]);
        }

        // 8. Action dispatch
        $result = $this->dispatcher->dispatch($endpoint, $mapped['data'], $rawPayload);

        if ($result['ok']) {
            $this->logger->info('inbound_action_succeeded',
                "Inbound action '{$endpoint->action_type}' succeeded on {$endpoint->handle}",
                array_merge($logCtx, ['action_type' => $endpoint->action_type]));
        }

        // 9. Response
        return $this->responder->build(
            $endpoint,
            (bool) $result['ok'],
            array_merge(
                ['message' => $result['message'] ?? null],
                $result['data'] ?? [],
            ),
        );
    }

    /**
     * Requests per minute this endpoint accepts. 0 disables throttling.
     *
     * An endpoint's own `rate_limit_config.per_minute` wins over the global
     * default, so one chatty provider can be given a wider (or narrower) lane
     * without loosening the limit for every other endpoint.
     */
    protected function rateLimitFor(InboundEndpoint $endpoint): int
    {
        $perEndpoint = ((array) ($endpoint->rate_limit_config ?? []))['per_minute'] ?? null;

        if ($perEndpoint !== null && $perEndpoint !== '') {
            return max(0, (int) $perEndpoint);
        }

        return max(0, (int) config('webhook-manager.inbound.rate_limit_per_minute', 0));
    }

    /**
     * Keyed by endpoint, not by caller IP.
     *
     * The limit an operator sets in the CP reads "Rate limit (per minute)" on
     * an endpoint, so that is what it has to mean. Keying by IP would let a
     * distributed sender multiply the limit by its fleet size, and the point of
     * the control is to cap what this endpoint's downstream actions have to
     * absorb. The id (not the handle) is the key so a rename does not silently
     * reset a live counter.
     */
    protected function rateLimitKey(InboundEndpoint $endpoint): string
    {
        return 'webhook-manager:inbound:'.$endpoint->id;
    }

    /** @return array<string, string> */
    protected function rateLimitHeaders(int $limit, int $remaining): array
    {
        return [
            'X-RateLimit-Limit' => (string) $limit,
            'X-RateLimit-Remaining' => (string) $remaining,
        ];
    }

    /**
     * Replay key: prefer an explicit idempotency header, then the HMAC
     * signature header (also unique per request), otherwise hash the body.
     *
     * TODO: REVIEW — making the header configurable per endpoint is a
     * v2 candidate; the body hash fallback covers the no-config case.
     */
    protected function replayKey(Request $request, array $payload, InboundEndpoint $endpoint): string
    {
        $idempotency = (string) $request->header('Idempotency-Key', '');
        if ($idempotency !== '') {
            return "endpoint:{$endpoint->id}:idempotency:{$idempotency}";
        }
        $sig = (string) $request->header('X-Webhook-Signature',
            (string) $request->header('X-Hub-Signature-256', ''));
        if ($sig !== '') {
            return "endpoint:{$endpoint->id}:sig:{$sig}";
        }
        return "endpoint:{$endpoint->id}:body:".sha1((string) $request->getContent());
    }

    /**
     * Build a uniform error response that still respects the endpoint's
     * configured failure status when the failure is at the action layer
     * rather than at validation.
     */
    protected function error(InboundEndpoint $endpoint, string $message, int $statusOverride, array $data = []): JsonResponse
    {
        // For pre-action failures (validation/auth), use the explicit
        // HTTP status — the endpoint's `failure_status` is reserved for
        // action-layer failures handled by the response builder.
        return response()->json([
            'ok' => false,
            'error' => $message,
            'data' => $data,
        ], $statusOverride);
    }
}
