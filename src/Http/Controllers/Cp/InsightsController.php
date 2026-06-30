<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Services\DeliveryStatsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Statamic\Http\Controllers\CP\CpController;

/**
 * Observability / Insights screen — delivery telemetry rendered as native
 * Statamic widgets and lightweight inline-SVG charts.
 */
class InsightsController extends CpController
{
    /** Day ranges offered by the range picker. */
    protected const RANGES = [7, 14, 30, 90];

    public function index(Request $request, DeliveryStatsService $stats): Response
    {
        abort_unless(
            $request->user()?->can('view webhook deliveries'),
            403
        );

        $days = (int) $request->integer('days', 30);
        if (! in_array($days, self::RANGES, true)) {
            $days = 30;
        }

        $webhookId = $request->integer('webhook') ?: null;

        return Inertia::render('webhook-manager::Insights/Index', [
            'stats' => $stats->build($days, $webhookId),
            'days' => $days,
            'webhookId' => $webhookId,
            'rangeOptions' => array_map(
                fn (int $d) => ['value' => $d, 'label' => trans_choice('webhook-manager::messages.insights_range_days', $d, ['count' => $d])],
                self::RANGES,
            ),
            'webhookOptions' => $this->webhookOptions(),
            'baseUrl' => cp_route('webhook-manager.insights'),
            'deliveriesUrl' => cp_route('webhook-manager.deliveries.index'),
        ]);
    }

    /**
     * Outbound hooks for the per-webhook filter, with an "all" sentinel.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function webhookOptions(): array
    {
        // 'all' (not 0) is the sentinel: the native Select treats a 0 value as
        // "nothing selected" and falls back to its placeholder. integer('all')
        // resolves to 0 → null in index(), so the filter still clears cleanly.
        $options = [['value' => 'all', 'label' => __('webhook-manager::messages.insights_all_webhooks')]];

        foreach (OutboundWebhook::query()->orderBy('name')->get(['id', 'name']) as $hook) {
            $options[] = ['value' => $hook->id, 'label' => $hook->name];
        }

        return $options;
    }
}
