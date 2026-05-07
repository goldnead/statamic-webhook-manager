<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Repositories\DeliveryRepository;
use Goldnead\WebhookManager\Repositories\InboundEndpointRepository;
use Goldnead\WebhookManager\Repositories\OutboundWebhookRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Http\Controllers\CP\CpController;

class OverviewController extends CpController
{
    public function index(
        Request $request,
        OutboundWebhookRepository $outbound,
        InboundEndpointRepository $inbound,
        DeliveryRepository $deliveries,
    ) {
        abort_unless($request->user()?->can('view webhooks'), 403);

        $counts = $deliveries->counts();
        $recentFailures = Delivery::query()
            ->where('status', Delivery::STATUS_FAILED)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['id', 'uuid', 'trigger_type', 'request_url', 'error_type', 'created_at'])
            ->map(fn (Delivery $d) => [
                'id' => $d->id,
                'uuid' => $d->uuid,
                'trigger_type' => $d->trigger_type,
                'request_url' => $d->request_url,
                'error_type' => $d->error_type,
                'created_at_human' => $d->created_at?->diffForHumans(),
                'show_url' => cp_route('webhook-manager.deliveries.show', $d),
            ]);

        return Inertia::render('webhook-manager::Overview/Index', [
            'activeOutbound' => $outbound->countActive(),
            'activeInbound' => $inbound->countActive(),
            'counts' => $counts,
            'successRate24h' => $deliveries->successRate(24),
            'successRate7d' => $deliveries->successRate(24 * 7),
            'recentFailures' => $recentFailures,
        ]);
    }
}
