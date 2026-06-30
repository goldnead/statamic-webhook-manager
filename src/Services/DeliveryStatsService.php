<?php

namespace Goldnead\WebhookManager\Services;

use Carbon\CarbonImmutable;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Illuminate\Support\Collection;

/**
 * Aggregates delivery telemetry for the Insights screen.
 *
 * All time-bucketing and percentile maths are done in PHP from a lean
 * column selection rather than via database-specific date/percentile
 * functions, so the same code runs identically on SQLite (tests,
 * playground), MySQL and Postgres. The query window is bounded by the
 * requested day range (and pruning keeps the table small), so the row
 * count stays manageable for typical installs.
 */
class DeliveryStatsService
{
    /**
     * Build the full Insights payload for the given window.
     *
     * @param  int  $days  Trailing window in days (inclusive of today).
     * @param  int|null  $webhookId  Optional filter to a single outbound hook.
     * @return array<string,mixed>
     */
    public function build(int $days = 30, ?int $webhookId = null): array
    {
        $days = max(1, min($days, 365));
        $since = CarbonImmutable::now()->startOfDay()->subDays($days - 1);

        $rows = $this->rows($since, $webhookId);

        return [
            'range_days' => $days,
            'summary' => $this->summary($rows),
            'timeseries' => $this->timeseries($rows, $since, $days),
            'latency' => $this->latency($rows),
            'errors' => $this->errorBreakdown($rows),
            'top_failing' => $this->topFailing($rows),
        ];
    }

    /**
     * Lean column selection for the window — only what the aggregations
     * below need, so a wide deliveries table doesn't blow up memory.
     *
     * @return Collection<int,object>
     */
    protected function rows(CarbonImmutable $since, ?int $webhookId): Collection
    {
        return Delivery::query()
            ->where('created_at', '>=', $since)
            ->when($webhookId, fn ($q) => $q->where('outbound_webhook_id', $webhookId))
            ->get(['status', 'created_at', 'duration_ms', 'error_type', 'outbound_webhook_id', 'request_url']);
    }

    /**
     * @param  Collection<int,object>  $rows
     * @return array<string,mixed>
     */
    protected function summary(Collection $rows): array
    {
        $total = $rows->count();
        $success = $rows->where('status', Delivery::STATUS_SUCCESS)->count();
        $failed = $rows->where('status', Delivery::STATUS_FAILED)->count();
        $pending = $total - $success - $failed;

        return [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'pending' => max(0, $pending),
            'success_rate' => $total > 0 ? round(($success / $total) * 100, 1) : 0.0,
        ];
    }

    /**
     * One bucket per day across the whole window (gap-filled with zeros so
     * the chart has a continuous x-axis even on quiet days).
     *
     * @param  Collection<int,object>  $rows
     * @return array<int,array<string,mixed>>
     */
    protected function timeseries(Collection $rows, CarbonImmutable $since, int $days): array
    {
        $buckets = [];
        for ($i = 0; $i < $days; $i++) {
            $key = $since->addDays($i)->toDateString();
            $buckets[$key] = ['date' => $key, 'total' => 0, 'success' => 0, 'failed' => 0];
        }

        foreach ($rows as $row) {
            $key = CarbonImmutable::parse($row->created_at)->toDateString();
            if (! isset($buckets[$key])) {
                continue;
            }
            $buckets[$key]['total']++;
            if ($row->status === Delivery::STATUS_SUCCESS) {
                $buckets[$key]['success']++;
            } elseif ($row->status === Delivery::STATUS_FAILED) {
                $buckets[$key]['failed']++;
            }
        }

        return array_map(function (array $b) {
            $b['success_rate'] = $b['total'] > 0
                ? round(($b['success'] / $b['total']) * 100, 1)
                : null;

            return $b;
        }, array_values($buckets));
    }

    /**
     * Latency percentiles over successful + failed attempts that recorded a
     * duration. Percentiles are computed in PHP (SQLite has no PERCENTILE).
     *
     * @param  Collection<int,object>  $rows
     * @return array<string,int|null>
     */
    protected function latency(Collection $rows): array
    {
        $durations = $rows
            ->pluck('duration_ms')
            ->filter(fn ($d) => $d !== null && $d >= 0)
            ->map(fn ($d) => (int) $d)
            ->sort()
            ->values();

        if ($durations->isEmpty()) {
            return ['p50' => null, 'p95' => null, 'p99' => null, 'max' => null];
        }

        return [
            'p50' => $this->percentile($durations, 50),
            'p95' => $this->percentile($durations, 95),
            'p99' => $this->percentile($durations, 99),
            'max' => $durations->last(),
        ];
    }

    /**
     * Nearest-rank percentile on an already-sorted, zero-indexed collection.
     *
     * @param  Collection<int,int>  $sorted
     */
    protected function percentile(Collection $sorted, int $p): int
    {
        $count = $sorted->count();
        $rank = (int) ceil(($p / 100) * $count);
        $index = max(0, min($count - 1, $rank - 1));

        return (int) $sorted->get($index);
    }

    /**
     * Failure counts grouped by classified error type, biggest first.
     *
     * @param  Collection<int,object>  $rows
     * @return array<int,array<string,mixed>>
     */
    protected function errorBreakdown(Collection $rows): array
    {
        return $rows
            ->where('status', Delivery::STATUS_FAILED)
            ->groupBy(fn ($row) => $row->error_type ?: 'unknown')
            ->map(fn (Collection $group, string $type) => [
                'type' => $type,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * Endpoints with the most terminal failures in the window, resolved to
     * the owning hook's name where possible.
     *
     * @param  Collection<int,object>  $rows
     * @return array<int,array<string,mixed>>
     */
    protected function topFailing(Collection $rows): array
    {
        $failed = $rows->where('status', Delivery::STATUS_FAILED);

        if ($failed->isEmpty()) {
            return [];
        }

        $names = OutboundWebhook::query()
            ->whereIn('id', $failed->pluck('outbound_webhook_id')->filter()->unique()->all())
            ->pluck('name', 'id');

        return $failed
            ->groupBy(fn ($row) => $row->outbound_webhook_id ?: 'url:'.$row->request_url)
            ->map(function (Collection $group) use ($names) {
                $first = $group->first();

                return [
                    'webhook_id' => $first->outbound_webhook_id,
                    'name' => $names[$first->outbound_webhook_id] ?? null,
                    'url' => $first->request_url,
                    'failures' => $group->count(),
                ];
            })
            ->sortByDesc('failures')
            ->take(8)
            ->values()
            ->all();
    }
}
