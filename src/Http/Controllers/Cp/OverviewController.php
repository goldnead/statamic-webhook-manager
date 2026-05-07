<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Repositories\DeliveryRepository;
use Goldnead\WebhookManager\Repositories\InboundEndpointRepository;
use Goldnead\WebhookManager\Repositories\OutboundWebhookRepository;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OverviewController extends Controller
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
            ->get();

        return view('webhook-manager::cp.overview.index', [
            'activeOutbound' => $outbound->countActive(),
            'activeInbound' => $inbound->countActive(),
            'counts' => $counts,
            'successRate24h' => $deliveries->successRate(24),
            'successRate7d' => $deliveries->successRate(24 * 7),
            'recentFailures' => $recentFailures,
        ]);
    }
}
