<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Goldnead\WebhookManager\Repositories\DeliveryRepository;
use Goldnead\WebhookManager\Repositories\InboundEndpointRepository;
use Goldnead\WebhookManager\Repositories\OutboundWebhookRepository;
use Goldnead\WebhookManager\Repositories\RuleRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Http\Controllers\CP\CpController;

class OverviewController extends CpController
{
    public function index(
        Request $request,
        OutboundWebhookRepository $outboundRepo,
        InboundEndpointRepository $inboundRepo,
        RuleRepository $ruleRepo,
        DeliveryRepository $deliveryRepo,
        TriggerRegistry $triggers,
    ) {
        $this->authorizeAny($request, 'manage outbound webhooks', 'manage inbound endpoints', 'view webhooks');

        $outboundCount = $outboundRepo->countActive();
        $inboundCount = $inboundRepo->countActive();
        $rulesCount = $ruleRepo->countActive();

        $counts = $deliveryRepo->counts();
        $successRate24h = $deliveryRepo->successRate(24);
        $successRate7d = $deliveryRepo->successRate(24 * 7);

        $triggerLabels = $triggers->options();

        return Inertia::render('webhook-manager::Overview/Index', [
            // Stat-Cards configured server-side so Vue stays presentational.
            // The repository surfaces successRate24h/7d already; failures
            // are derived from counts (no dedicated repo method needed).
            'stats' => [
                [
                    'key'   => 'outbound_active',
                    'icon'  => 'outgoing',
                    'label' => __('Active Outbound'),
                    'value' => (string) $outboundCount,
                    'trend' => null,
                ],
                [
                    'key'   => 'inbound_active',
                    'icon'  => 'incoming',
                    'label' => __('Active Inbound'),
                    'value' => (string) $inboundCount,
                    'trend' => null,
                ],
                [
                    'key'   => 'success_rate_24h',
                    'icon'  => 'check-circle',
                    'label' => __('Success rate (24h)'),
                    'value' => $successRate24h.'%',
                    'trend' => null,
                ],
                [
                    'key'   => 'failures_total',
                    'icon'  => 'exclamation-triangle',
                    'label' => __('Failed deliveries'),
                    'value' => (string) ($counts['failed'] ?? 0),
                    'trend' => null,
                ],
            ],
            'recentFailures' => $this->buildRecentFailures($triggerLabels),
            'failureColumns' => [
                ['handle' => 'when',    'label' => __('When'),    'visible' => true, 'sortable' => false],
                ['handle' => 'trigger', 'label' => __('Trigger'), 'visible' => true, 'sortable' => false],
                ['handle' => 'url',     'label' => __('URL'),     'visible' => true, 'sortable' => false],
                ['handle' => 'status',  'label' => __('Error'),   'visible' => true, 'sortable' => false],
            ],
            'isEmpty' => $outboundCount === 0 && $inboundCount === 0 && $rulesCount === 0,
            'counts' => $counts,
            'successRate7d' => $successRate7d,

            // Create URLs (gated by canCreate* flags below)
            'createOutboundUrl' => cp_route('webhook-manager.outbound.create'),
            'createInboundUrl'  => cp_route('webhook-manager.inbound.create'),
            'createRuleUrl'     => cp_route('webhook-manager.rules.create'),

            // Navigation URLs
            'outboundUrl'   => cp_route('webhook-manager.outbound.index'),
            'inboundUrl'    => cp_route('webhook-manager.inbound.index'),
            'rulesUrl'      => cp_route('webhook-manager.rules.index'),
            'deliveriesUrl' => cp_route('webhook-manager.deliveries.index'),
            'logsUrl'       => cp_route('webhook-manager.logs.index'),

            // Pre-computed permission flags so v-if stays declarative.
            'canCreateOutbound' => (bool) $request->user()?->can('manage outbound webhooks'),
            'canCreateInbound'  => (bool) $request->user()?->can('manage inbound endpoints'),
            'canCreateRule'     => (bool) $request->user()?->can('manage rules'),
        ]);
    }

    /**
     * Last 8 failed deliveries, shaped for the <Listing> component.
     *
     * @param  array<string,string>  $triggerLabels
     * @return array{data:array<int,array<string,mixed>>,meta:array<string,int>}
     */
    private function buildRecentFailures(array $triggerLabels): array
    {
        $failures = Delivery::query()
            ->where('status', Delivery::STATUS_FAILED)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['id', 'uuid', 'trigger_type', 'request_url', 'error_type', 'created_at']);

        $rows = $failures->map(fn (Delivery $d) => [
            'id' => $d->id,
            'uuid' => $d->uuid,
            'when' => $d->created_at?->toIso8601String(),
            'trigger' => $d->trigger_type,
            'trigger_label' => $triggerLabels[$d->trigger_type] ?? $d->trigger_type,
            'url' => $d->request_url,
            'status' => $d->error_type ?? 'failed',
            'show_url' => cp_route('webhook-manager.deliveries.show', $d),
        ])->values()->all();

        return [
            'data' => $rows,
            'meta' => [
                'total' => count($rows),
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 8,
                'from' => count($rows) > 0 ? 1 : 0,
                'to' => count($rows),
            ],
        ];
    }

    private function authorizeAny(Request $request, string ...$abilities): void
    {
        $user = $request->user();
        foreach ($abilities as $ability) {
            if ($user?->can($ability)) {
                return;
            }
        }
        abort(403);
    }
}
