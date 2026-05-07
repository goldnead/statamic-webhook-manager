<?php

namespace Goldnead\WebhookManager\Http\Controllers;

use Goldnead\WebhookManager\Repositories\InboundEndpointRepository;
use Goldnead\WebhookManager\Services\Inbound\InboundRequestProcessor;
use Goldnead\WebhookManager\Services\Logging\SystemLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Inbound endpoint controller.
 *
 * TODO: REVIEW — endpoint resolution, auth verification, parsing, mapping
 * and action dispatch are wired through but currently return
 * 501 Not Implemented. The full pipeline is on the iterative roadmap and
 * the surrounding services (InboundRequestProcessor, mapping engine,
 * auth verifiers) are already implemented.
 */
class InboundWebhookController extends Controller
{
    public function __construct(
        protected InboundEndpointRepository $endpoints,
        protected InboundRequestProcessor $processor,
        protected SystemLogger $logger,
    ) {
    }

    public function __invoke(Request $request, string $handle): JsonResponse
    {
        $endpoint = $this->endpoints->findByHandle($handle);

        if (! $endpoint || ! $endpoint->enabled) {
            return response()->json([
                'ok' => false,
                'error' => 'Endpoint not found or disabled.',
            ], 404);
        }

        $this->logger->info('inbound_received', "Inbound request for {$handle}", [
            'endpoint_id' => $endpoint->id,
            'method' => $request->method(),
        ]);

        // TODO: REVIEW — flip on once the inbound flow ships.
        return response()->json([
            'ok' => false,
            'error' => __('webhook-manager::messages.errors.inbound_not_implemented'),
        ], 501);
    }
}
