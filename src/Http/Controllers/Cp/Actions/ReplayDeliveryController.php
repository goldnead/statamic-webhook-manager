<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp\Actions;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Jobs\ReplayDeliveryJob;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ReplayDeliveryController extends Controller
{
    public function __invoke(Request $request, Delivery $delivery)
    {
        abort_unless($request->user()?->can('replay webhook deliveries'), 403);

        $reRender = (bool) $request->boolean('re_render');
        ReplayDeliveryJob::dispatch($delivery->id, $reRender)
            ->onConnection(config('webhook-manager.queue.connection'))
            ->onQueue(config('webhook-manager.queue.name', 'default'));

        return response()->json([
            'ok' => true,
            'message' => __('webhook-manager::messages.replayed'),
        ]);
    }
}
