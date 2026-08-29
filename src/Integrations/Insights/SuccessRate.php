<?php

namespace Goldnead\WebhookManager\Integrations\Insights;

use Goldnead\StatamicInsights\Support\MetricQuery;
use Goldnead\StatamicInsights\Support\Unit;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Services\DeliveryStatsService;

/**
 * Von den Zustellungen mit einem Urteil: wie viele sind angekommen.
 *
 * **Der Nenner ist `erfolgreich + gescheitert`, nicht „alle Zustellungen".** Was
 * noch auf einen Wiederholungsversuch wartet, hat kein Urteil; was jemand
 * abgebrochen hat, hat eines, aber keines ueber die Gegenstelle. Beides in den
 * Nenner zu nehmen liesse die Quote in dem Moment fallen, in dem sich ein
 * Rueckstau bildet — also genau dann, wenn noch gar nichts schiefgegangen ist.
 *
 * Das weicht bewusst vom `summary.success_rate` des eigenen Schirms ab
 * ({@see DeliveryStatsService}), der
 * `erfolgreich / alle` rechnet. Der Grund ist der gemeinsame Schirm: dort steht
 * „Erfolgsquote" neben derselben Zahl aus dem Automations-Addon, und zwei
 * Kacheln mit demselben Wort und verschiedener Bedeutung sind schlimmer als
 * eine, die von der Detailansicht abweicht. Die Abweichung ist hier benannt;
 * eine stille waere es nicht.
 *
 * **Null ist nicht null Prozent.** Ohne eine einzige entschiedene Zustellung
 * gibt es keine Antwort. `0 %` waere eine Aussage ueber Zustellungen, die es
 * nicht gab, und stuende neben einer Zustellzahl von null, die ihr
 * widerspricht. Der eigene Schirm liefert dort heute `0.0`; hier ist es `null`.
 */
class SuccessRate extends WebhookMetric
{
    public function handle(): string
    {
        return 'webhooks.success_rate';
    }

    public function label(): string
    {
        return __('webhook-manager::insights.success_rate');
    }

    public function description(): ?string
    {
        return __('webhook-manager::insights.success_rate_description');
    }

    public function unit(): string
    {
        return Unit::PERCENT;
    }

    public function value(MetricQuery $query): int|float|null
    {
        if (! $this->available()) {
            return null;
        }

        $row = $this->untilNow($query)
            ->selectRaw($this->verdictCounts(), $this->verdictBindings())
            ->first();

        return $this->rate((int) ($row->succeeded ?? 0), (int) ($row->failed ?? 0));
    }

    /**
     * Eine Quote je Eimer, und `null`, wo es nichts zu teilen gibt.
     *
     * Ausgelassen waere falsch: der Vertrag fuellt ausgelassene Eimer mit einer
     * Null auf, und eine Null ist bei einer Quote die Behauptung „an diesem Tag
     * kam nichts an". `null` heisst „an diesem Tag wurde nichts entschieden".
     */
    public function series(MetricQuery $query): array
    {
        if (! $this->available()) {
            return [];
        }

        $rows = $this->untilNow($query)
            ->selectRaw($this->bucketExpression($query).' as bucket, '.$this->verdictCounts(), $this->verdictBindings())
            ->groupBy('bucket')
            ->get();

        $buckets = [];

        foreach ($rows as $row) {
            $buckets[(string) $row->bucket] = $this->rate((int) $row->succeeded, (int) $row->failed);
        }

        ksort($buckets);

        return $buckets;
    }

    /** Eine Nachkommastelle, wie im eigenen Schirm dieses Addons auch. */
    protected function rate(int $succeeded, int $failed): ?float
    {
        $decided = $succeeded + $failed;

        return $decided > 0 ? round($succeeded / $decided * 100, 1) : null;
    }

    /** Beide Haelften in einer Abfrage: zwei waeren zwei Gelegenheiten zu filtern. */
    protected function verdictCounts(): string
    {
        return 'sum(case when status = ? then 1 else 0 end) as succeeded, '
            .'sum(case when status = ? then 1 else 0 end) as failed';
    }

    /** @return array<int, string> */
    protected function verdictBindings(): array
    {
        return [Delivery::STATUS_SUCCESS, Delivery::STATUS_FAILED];
    }
}
