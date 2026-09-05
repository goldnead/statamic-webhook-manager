<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Contracts\Repositories\InboundEndpointRepositoryInterface;
use Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface;
use Goldnead\WebhookManager\Contracts\Repositories\RuleRepositoryInterface;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Http\Controllers\Cp\Concerns\PresentsOutboundWebhooks;
use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Goldnead\WebhookManager\Repositories\DeliveryRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Http\Controllers\CP\CpController;

class OverviewController extends CpController
{
    use PresentsOutboundWebhooks;

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

        $webhooks = $outboundRepo->all()
            ->map(fn (OutboundWebhook $hook) => $this->outboundRow($hook, $request, $triggerLabels))
            ->values()
            ->all();

        return Inertia::render('webhook-manager::Overview/Index', [
            // The four numbers, as rows of a two-column table rather than a
            // grid of cards. Statamic has no card grid for figures anywhere on
            // a sub-page — cards live on the dashboard as widgets, everything
            // else is a table (approval-insights-kennzahlen-darstellung, Weg A,
            // decided 03.09.2026).
            'stats' => [
                [
                    'key' => 'outbound_active',
                    'icon' => 'arrow-up-right',
                    'label' => __('webhook-manager::messages.cp.stat_outbound_active'),
                    'value' => (string) $outboundCount,
                    'url' => cp_route('webhook-manager.outbound.index'),
                ],
                [
                    'key' => 'inbound_active',
                    'icon' => 'download',
                    'label' => __('webhook-manager::messages.cp.stat_inbound_active'),
                    'value' => (string) $inboundCount,
                    'url' => cp_route('webhook-manager.inbound.index'),
                ],
                [
                    'key' => 'success_rate_24h',
                    'icon' => 'checkmark',
                    'label' => __('webhook-manager::messages.cp.stat_success_rate'),
                    'value' => $successRate24h.' %',
                    'url' => cp_route('webhook-manager.insights'),
                ],
                [
                    'key' => 'failures_total',
                    'icon' => 'warning-diamond',
                    'label' => __('webhook-manager::messages.cp.stat_failures'),
                    'value' => (string) ($counts['failed'] ?? 0),
                    'url' => cp_route('webhook-manager.deliveries.index'),
                ],
            ],

            // The webhooks themselves. Until now the overview showed four
            // numbers and a failure log and not a single one of the objects
            // the screen is about. Plain array, not a paginated payload:
            // <Listing> renders it client-side through `:items`.
            'webhooks' => $webhooks,
            'webhookColumns' => $this->outboundColumns(),
            'outboundIndexUrl' => cp_route('webhook-manager.outbound.index'),
            'outboundActionUrl' => cp_route('webhook-manager.outbound.actions.run'),

            'recentFailures' => $this->buildRecentFailures($triggerLabels),
            'failureColumns' => [
                ['field' => 'when', 'label' => __('webhook-manager::messages.cp.col_when'), 'visible' => true, 'sortable' => true],
                ['field' => 'trigger', 'label' => __('webhook-manager::messages.cp.col_trigger'), 'visible' => true, 'sortable' => true],
                ['field' => 'url', 'label' => __('webhook-manager::messages.cp.col_url'), 'visible' => true, 'sortable' => false],
                ['field' => 'status', 'label' => __('webhook-manager::messages.cp.col_error'), 'visible' => true, 'sortable' => true],
            ],
            'deliveriesIndexUrl' => cp_route('webhook-manager.deliveries.index'),
            'deliveryActionUrl' => cp_route('webhook-manager.deliveries.actions.run'),

            'isEmpty' => $outboundCount === 0 && $inboundCount === 0 && $rulesCount === 0,

            // Create URLs (gated by canCreate* flags below)
            'createOutboundUrl' => cp_route('webhook-manager.outbound.create'),
            'createInboundUrl' => cp_route('webhook-manager.inbound.create'),
            'createRuleUrl' => cp_route('webhook-manager.rules.create'),

            // Pre-computed permission flags so v-if stays declarative.
            'canCreateOutbound' => (bool) $request->user()?->can('manage outbound webhooks'),
            'canCreateInbound' => (bool) $request->user()?->can('manage inbound endpoints'),
            'canCreateRule' => (bool) $request->user()?->can('manage webhook rules'),
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
        $canReplay = (bool) request()->user()?->can('replay webhook deliveries');

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
                'can_replay' => $canReplay,
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
