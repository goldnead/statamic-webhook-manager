<?php

namespace Goldnead\WebhookManager\Services\Inbound;

use Goldnead\WebhookManager\Auth\Support\ReplayProtectionService;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Goldnead\WebhookManager\Services\Logging\SystemLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Orchestrates the inbound pipeline:
 *
 *   1. allowed-method check         → 405 on failure
 *   2. payload size check           → 413 on failure
 *   3. auth verification            → 401 on failure
 *   4. content-type / parsing       → 400 on failure
 *   5. replay protection (optional) → 409 on failure
 *   6. mapping engine               → 422 on failure
 *   7. action dispatch              → 422/200 from action result
 *   8. response builder             → final JSON response
 *
 * Each failure short-circuits the pipeline and writes a structured
 * SystemLogger entry so failed requests are reviewable in the CP without
 * having to scrape webserver logs.
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

        // 1. Method allowlist
        $allowed = array_map('strtoupper', (array) ($endpoint->allowed_methods ?? ['POST']));
        if ($allowed && ! in_array(strtoupper($request->method()), $allowed, true)) {
            $this->logger->warning('inbound_method_not_allowed',
                "Method {$request->method()} not allowed on {$endpoint->handle}", $logCtx);
            return $this->error($endpoint, 'Method not allowed.', 405);
        }

        // 2. Payload size
        $maxKb = (int) ($endpoint->max_payload_kb ?? config('webhook-manager.inbound.max_payload_kb', 512));
        if ($maxKb > 0) {
            $bodyLen = strlen((string) $request->getContent());
            if ($bodyLen > $maxKb * 1024) {
                $this->logger->warning('inbound_payload_too_large',
                    "Payload {$bodyLen}B exceeds {$maxKb}KB on {$endpoint->handle}", $logCtx);
                return $this->error($endpoint, 'Payload too large.', 413);
            }
        }

        // 3. Auth
        if (! $this->auth->verify($request, $endpoint)) {
            $this->logger->warning('inbound_auth_failed',
                "Auth failed on {$endpoint->handle} ({$endpoint->auth_type})", $logCtx);
            return $this->error($endpoint, 'Unauthorized.', 401);
        }

        // 4. Parse
        $contentType = (string) ($endpoint->expected_content_type ?? 'application/json');
        $parsed = $this->parser->parse($request, $contentType);
        if (! $parsed['ok']) {
            $this->logger->warning('inbound_parse_failed', $parsed['error'] ?? 'Parse failed', $logCtx);
            return $this->error($endpoint, $parsed['error'] ?? 'Bad request.', 400);
        }
        $rawPayload = $parsed['data'];

        // 5. Replay protection (optional)
        if ($endpoint->replay_protection_enabled) {
            $key = $this->replayKey($request, $rawPayload, $endpoint);
            if (! $this->replay->check($key)) {
                $this->logger->warning('inbound_replay_blocked',
                    "Replay-protected request rejected on {$endpoint->handle}", $logCtx);
                return $this->error($endpoint, 'Duplicate request.', 409);
            }
        }

        // 6. Mapping
        $mapped = $this->mapping->map($endpoint->mapping_config ?? null, $rawPayload);
        if (! $mapped['ok']) {
            $this->logger->warning('inbound_mapping_failed',
                'Mapping errors: '.implode('; ', $mapped['errors'] ?? []), $logCtx);
            return $this->error($endpoint, 'Mapping failed.', 422, [
                'errors' => $mapped['errors'] ?? [],
            ]);
        }

        // 7. Action dispatch
        $result = $this->dispatcher->dispatch($endpoint, $mapped['data'], $rawPayload);

        if ($result['ok']) {
            $this->logger->info('inbound_action_succeeded',
                "Inbound action '{$endpoint->action_type}' succeeded on {$endpoint->handle}",
                array_merge($logCtx, ['action_type' => $endpoint->action_type]));
        }

        // 8. Response
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
