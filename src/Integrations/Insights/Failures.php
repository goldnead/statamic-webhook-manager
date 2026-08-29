<?php

namespace Goldnead\WebhookManager\Integrations\Insights;

use Goldnead\StatamicInsights\Support\MetricQuery;
use Goldnead\StatamicInsights\Support\Unit;

/**
 * Wie viele Zustellungen endgueltig gescheitert sind.
 *
 * Nur `failed`. Eine Zustellung, die noch auf einen Wiederholungsversuch
 * wartet, steht auf `pending` und ist kein Fehlschlag — sie als einen zu
 * zaehlen faerbt jede Stunde rot, in der ein fremder Dienst kurz nicht da war
 * und danach wieder. Eine abgebrochene Zustellung ist auch keiner: die hat
 * jemand absichtlich angehalten.
 *
 * Warum sie sich hier nicht nach Fehlerklasse aufteilen laesst, steht in
 * {@see WebhookMetric}: das kann der eigene Schirm des Addons, und dort
 * gehoert es hin.
 */
class Failures extends WebhookMetric
{
    public function handle(): string
    {
        return 'webhooks.failures';
    }

    public function label(): string
    {
        return __('webhook-manager::insights.failures');
    }

    public function description(): ?string
    {
        return __('webhook-manager::insights.failures_description');
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

        return (int) $this->failedInPeriod($query)->count();
    }

    public function series(MetricQuery $query): array
    {
        if (! $this->available()) {
            return [];
        }

        return array_map(
            fn ($measured) => (int) $measured,
            $this->bucketed($this->failedInPeriod($query), $query, 'count(*)'),
        );
    }
}
