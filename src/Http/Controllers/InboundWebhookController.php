<?php

namespace Goldnead\WebhookManager\Http\Controllers;

use Goldnead\WebhookManager\Contracts\Repositories\InboundEndpointRepositoryInterface;
use Goldnead\WebhookManager\Services\Inbound\InboundRequestProcessor;
use Goldnead\WebhookManager\Services\Logging\SystemLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Public-facing inbound webhook controller.
 *
 * Thin: resolves the endpoint, then delegates the entire pipeline
 * (auth → parse → replay → map → action → response) to the
 * `InboundRequestProcessor`. Errors are produced uniformly by the
 * processor — this controller never builds bespoke responses except
 * for the lookup-failure case (404).
 */
class InboundWebhookController extends Controller
{
    public function __construct(
        protected InboundEndpointRepositoryInterface $endpoints,
        protected InboundRequestProcessor $processor,
        protected SystemLogger $logger,
    ) {
    }

    public function __invoke(Request $request, string $handle): JsonResponse
    {
        $endpoint = $this->endpoints->findByHandle($handle);

        if (! $endpoint || ! $endpoint->enabled) {
            $this->logger->warning('inbound_endpoint_not_found',
                "Inbound request for unknown or disabled endpoint '{$handle}'.", [
                    'handle' => $handle,
                    'method' => $request->method(),
                ]);

            return response()->json([
                'ok' => false,
                'error' => 'Endpoint not found or disabled.',
            ], 404);
        }

        $this->logger->info('inbound_received', "Inbound request for {$handle}", [
            'endpoint_id' => $endpoint->id,
            'method' => $request->method(),
            'content_length' => strlen((string) $request->getContent()),
        ]);

        return $this->processor->process($request, $endpoint);
    }
}
