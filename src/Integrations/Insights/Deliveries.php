<?php

namespace Goldnead\WebhookManager\Integrations\Insights;

use Goldnead\StatamicInsights\Contracts\HasBreakdowns;
use Goldnead\StatamicInsights\Support\MetricQuery;
use Goldnead\StatamicInsights\Support\Unit;

/**
 * Wie viele Zustellungen es gab.
 *
 * Zustellungen, nicht Versuche: eine Zeile ist ein Ereignis, das raus musste,
 * und `attempts` zaehlt, wie oft es dafuer geklopft hat. Die Aufteilung nach
 * Status ist die einzige, die hier angeboten wird — nach Fehlerklasse und nach
 * Endpunkt teilt der eigene Schirm des Addons auf, und der kann es besser.
 */
class Deliveries extends WebhookMetric implements HasBreakdowns
{
    public function handle(): string
    {
        return 'webhooks.deliveries';
    }

    public function label(): string
    {
        return __('webhook-manager::insights.deliveries');
    }

    public function description(): ?string
    {
        return __('webhook-manager::insights.deliveries_description');
    }

    public function unit(): string
    {
        return Unit::COUNT;
    }

    public function value(MetricQuery $query): int|float|null
    {
        if (! $this->available()) {
            return null;
        }

        return (int) $this->untilNow($query)->count();
    }

    public function series(MetricQuery $query): array
    {
        if (! $this->available()) {
            return [];
        }

        return array_map(
            fn ($measured) => (int) $measured,
            $this->bucketed($this->untilNow($query), $query, 'count(*)'),
        );
    }

    public function breakdowns(): array
    {
        return ['status' => __('webhook-manager::insights.breakdown_status')];
    }

    public function breakdown(MetricQuery $query, string $dimension, int $limit = 20): array
    {
        if (! $this->available() || $dimension !== 'status') {
            return [];
        }

        $rows = $this->splitByColumn($this->untilNow($query), $query, 'status', 'count(*)', $limit);

        return array_map(fn (array $row) => [
            'key' => $row['key'],
            'label' => $row['key'] === null ? $this->missingLabel('status') : $this->statusLabel($row['key']),
            'value' => $row['value'],
        ], $rows);
    }
}
