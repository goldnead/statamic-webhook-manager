<?php

namespace Goldnead\WebhookManager\Integrations\Insights;

use Goldnead\StatamicInsights\Support\MetricQuery;
use Goldnead\StatamicInsights\Support\Unit;
use Illuminate\Database\Query\Builder;

/**
 * Wie lange eine Zustellung im Schnitt gedauert hat.
 *
 * **Durchschnitt und nicht Median, und der Name sagt es.** Der eigene Schirm
 * dieses Addons rechnet p50, p95 und p99 aus denselben Zeilen, und die
 * Perzentile sind dort die bessere Antwort. Hier daneben noch einen zweiten
 * Median zu fuehren, hiesse dieselbe Frage an zwei Stellen zu beantworten und
 * darauf zu hoffen, dass beide dasselbe sagen. Der Durchschnitt ist die
 * einfachere Zahl, ist ehrlich benannt, und wer den Schwanz sehen will, ist im
 * eigenen Schirm ohnehin besser aufgehoben.
 *
 * **Millisekunden werden zu Sekunden.** `duration_ms` ist die gespeicherte
 * Spalte, {@see Unit::DURATION} sind Sekunden. Eine Nachkommastelle bleibt
 * stehen; wuerde hier auf ganze Sekunden gerundet, laese jede gesunde
 * Installation „0 s", denn eine Zustellung, die gut laeuft, dauert zwei- bis
 * dreihundert Millisekunden.
 *
 * Der gemeinsame Schirm rundet danach trotzdem auf ganze Sekunden, weil er das
 * fuer alle Zeitspannen so macht. Deshalb steht die exakte Millisekunde in
 * {@see self::meta()}: ein Schirm, der genauer sein will, hat die Zahl da, und
 * sie geht nicht verloren, nur weil die Formatierung sie nicht zeigt. Die
 * Beschreibung sagt es zusaetzlich in Worten — eine Kachel, die „0 s" zeigt und
 * nichts dazu, sieht aus wie ein Defekt.
 *
 * Zustellungen ohne `duration_ms` — noch nicht versucht, abgebrochen — sind
 * keine Null, sondern keine Messung, und bleiben aussen vor. Ein Rueckstau
 * verkuerzt die durchschnittliche Antwortzeit sonst genau dann, wenn nichts
 * antwortet.
 */
class LatencyAvg extends WebhookMetric
{
    public function handle(): string
    {
        return 'webhooks.latency_avg';
    }

    public function label(): string
    {
        return __('webhook-manager::insights.latency_avg');
    }

    public function description(): ?string
    {
        return __('webhook-manager::insights.latency_avg_description');
    }

    public function unit(): string
    {
        return Unit::DURATION;
    }

    /**
     * Die exakte Millisekunde, damit die Rundung auf Sekunden sie nicht
     * verschluckt. Kostet eine zweite Aggregat-Abfrage; dieselbe Wahl trifft
     * `RevenueGross` im Zahlungs-Addon fuer seine Positionssumme.
     *
     * @return array<string, mixed>
     */
    public function meta(MetricQuery $query): array
    {
        return ['duration_ms' => $this->averageMilliseconds($query)];
    }

    public function value(MetricQuery $query): int|float|null
    {
        $milliseconds = $this->averageMilliseconds($query);

        return $milliseconds === null ? null : round($milliseconds / 1000, 1);
    }

    public function series(MetricQuery $query): array
    {
        if (! $this->available()) {
            return [];
        }

        return array_map(
            fn ($measured) => round(((float) $measured) / 1000, 1),
            $this->bucketed($this->measured($query), $query, 'avg(duration_ms)'),
        );
    }

    /** Der Durchschnitt in Millisekunden, oder `null`, wenn nichts gemessen wurde. */
    protected function averageMilliseconds(MetricQuery $query): ?float
    {
        if (! $this->available()) {
            return null;
        }

        $row = $this->measured($query)
            ->selectRaw('avg(duration_ms) as average, count(*) as measured')
            ->first();

        return ((int) ($row->measured ?? 0)) > 0 ? (float) $row->average : null;
    }

    /** Zustellungen im Fenster, die eine Dauer aufgezeichnet haben. */
    protected function measured(MetricQuery $query): Builder
    {
        return $this->untilNow($query)->whereNotNull('duration_ms');
    }
}
