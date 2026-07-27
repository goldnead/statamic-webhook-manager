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

        // An Inertia visit cannot consume a bare JSON body. It treats the
        // non-Inertia 200 as "not mine", falls back to a hard
        // `window.location` visit to the same URL — a GET against a POST-only
        // route — and the user lands on a 404 while the replay has in fact
        // succeeded. That is the dangerous shape: it invites a second replay
        // of a delivery that already went out. Redirect back instead, so the
        // detail page reloads with the incremented attempt counter and a
        // success flash.
        if ($request->header('X-Inertia')) {
            return back()->with('success', __('webhook-manager::messages.replayed'));
        }

        return response()->json([
            'ok' => true,
            'message' => __('webhook-manager::messages.replayed'),
        ]);
    }
}
