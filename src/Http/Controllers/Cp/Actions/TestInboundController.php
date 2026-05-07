<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp\Actions;

use Goldnead\WebhookManager\Domain\InboundEndpoint\Actions\TestInboundEndpointAction;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * CP-side "Test endpoint" button.
 *
 * Bypasses the public HTTP layer entirely — the user is already authenticated
 * via the CP and authorised via permissions, so we don't re-run auth verification
 * or replay protection. The mapping and action layers run with a sample payload
 * provided by the editor so users can see exactly what would happen.
 */
class TestInboundController extends Controller
{
    public function __invoke(
        Request $request,
        InboundEndpoint $endpoint,
        TestInboundEndpointAction $test,
    ) {
        abort_unless($request->user()?->can('manage inbound endpoints'), 403);

        $payload = (array) $request->input('sample_payload', []);
        $result = ($test)($endpoint, $payload);

        return response()->json($result);
    }
}
