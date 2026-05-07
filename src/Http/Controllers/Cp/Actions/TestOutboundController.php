<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp\Actions;

use Goldnead\WebhookManager\Domain\OutboundWebhook\Actions\TestOutboundWebhookAction;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Http\Requests\TestWebhookRequest;
use Illuminate\Routing\Controller;

class TestOutboundController extends Controller
{
    public function __invoke(
        TestWebhookRequest $request,
        OutboundWebhook $webhook,
        TestOutboundWebhookAction $test,
    ) {
        abort_unless($request->user()?->can('manage outbound webhooks'), 403);

        $delivery = ($test)($webhook, (array) $request->input('sample_payload', []));

        return response()->json([
            'ok' => $delivery->status === \Goldnead\WebhookManager\Domain\Delivery\Models\Delivery::STATUS_SUCCESS,
            'delivery_id' => $delivery->id,
            'status' => $delivery->status,
            'response_status' => $delivery->response_status,
            'duration_ms' => $delivery->duration_ms,
            'error_message' => $delivery->error_message,
        ]);
    }
}
