<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Goldnead\WebhookManager\Repositories\DeliveryRepository;
use Goldnead\WebhookManager\Contracts\Repositories\InboundEndpointRepositoryInterface;
use Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface;
use Goldnead\WebhookManager\Contracts\Repositories\RuleRepositoryInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Http\Controllers\CP\CpController;

class OverviewController extends CpController
{
    public function index(
        Request $request,
        OutboundWebhookRepositoryInterface $outboundRepo,
        InboundEndpointRepositoryInterface $inboundRepo,
        RuleRepositoryInterface $ruleRepo,
        DeliveryRepository $deliveryRepo,
        TriggerRegistry $triggers,
    ) {
        $this->authorizeAny($request, 'manage outbound webhooks', 'manage inbound endpoints', 'view webhooks');

        $outboundCount = $outboundRepo->countActive();
        $inboundCount = $inboundRepo->countActive();
        $rulesCount = $ruleRepo->countActive();

        $counts = $deliveryRepo->counts();
        $successRate24h = $deliveryRepo->successRate(24);

        $triggerLabels = $triggers->options();

        return Inertia::render('webhook-manager::Overview/Index', [
            // Four stat cards. The successRate24h is rendered as a percentage
            // string, the counts are integers as strings (so the Vue layer
            // can render them with tabular-nums without coercion).
            'stats' => [
                [
                    'key' => 'outbound_active',
                    'icon' => 'arrow-up-right',
                    'label' => __('Active Outbound'),
                    'value' => (string) $outboundCount,
                ],
                [
                    'key' => 'inbound_active',
                    'icon' => 'download',
                    'label' => __('Active Inbound'),
                    'value' => (string) $inboundCount,
                ],
                [
                    'key' => 'success_rate_24h',
                    'icon' => 'checkmark',
                    'label' => __('Success rate (24h)'),
                    'value' => $successRate24h.'%',
                ],
                [
                    'key' => 'failures_total',
                    'icon' => 'warning-diamond',
                    'label' => __('Failed deliveries'),
                    'value' => (string) ($counts['failed'] ?? 0),
                ],
            ],
            // Plain array (NOT a paginated payload) — the Listing component
            // expects `:items` as an Array when used in client-side mode.
            'recentFailures' => $this->buildRecentFailures($triggerLabels),
            'failureColumns' => [
                ['field' => 'when',    'label' => __('When'),    'visible' => true, 'sortable' => false],
                ['field' => 'trigger', 'label' => __('Trigger'), 'visible' => true, 'sortable' => false],
                ['field' => 'url',     'label' => __('URL'),     'visible' => true, 'sortable' => false],
                ['field' => 'status',  'label' => __('Error'),   'visible' => true, 'sortable' => false],
            ],
            'isEmpty' => $outboundCount === 0 && $inboundCount === 0 && $rulesCount === 0,

            // Create URLs (gated by canCreate* flags below)
            'createOutboundUrl' => cp_route('webhook-manager.outbound.create'),
            'createInboundUrl' => cp_route('webhook-manager.inbound.create'),
            'createRuleUrl' => cp_route('webhook-manager.rules.create'),

            // Pre-computed permission flags so v-if stays declarative.
            'canCreateOutbound' => (bool) $request->user()?->can('manage outbound webhooks'),
            'canCreateInbound' => (bool) $request->user()?->can('manage inbound endpoints'),
            'canCreateRule' => (bool) $request->user()?->can('manage rules'),
        ]);
    }

    /**
     * Last 8 failed deliveries, shaped as a plain array of rows. The Vue
     * Listing component consumes this via `:items` and renders it
     * client-side (no pagination — Recent Failures is a fixed-size widget).
     *
     * @param  array<string,string>  $triggerLabels
     * @return array<int,array<string,mixed>>
     */
    private function buildRecentFailures(array $triggerLabels): array
    {
        return Delivery::query()
            ->where('status', Delivery::STATUS_FAILED)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['id', 'uuid', 'trigger_type', 'request_url', 'error_type', 'created_at'])
            ->map(fn (Delivery $d) => [
                'id' => $d->id,
                'uuid' => $d->uuid,
                'when' => $d->created_at?->toIso8601String(),
                'trigger' => $d->trigger_type,
                'trigger_label' => $triggerLabels[$d->trigger_type] ?? $d->trigger_type,
                'url' => $d->request_url,
                'status' => $d->error_type ?? 'failed',
                'show_url' => cp_route('webhook-manager.deliveries.show', $d),
            ])
            ->values()
            ->all();
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
