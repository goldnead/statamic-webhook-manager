<?php

namespace Goldnead\WebhookManager\Integrations\Insights;

use Goldnead\StatamicInsights\Support\MetricQuery;
use Goldnead\StatamicInsights\Support\TableMetric;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Http\Controllers\Cp\InsightsController;
use Goldnead\WebhookManager\Services\DeliveryStatsService;
use Illuminate\Database\Query\Builder;

/**
 * Was jede Zustellzahl dieses Addons gemeinsam hat.
 *
 * **Hier stehen absichtlich nur die Kernzahlen.** Dieses Addon hat einen eigenen
 * Auswertungsschirm ({@see InsightsController}
 * ueber {@see DeliveryStatsService}), und der
 * kann mehr, als ein gemeinsames Brett je koennen wird: Latenz-Perzentile
 * p50/p95/p99, Fehlerklassen nach `error_type`, die Endpunkte mit den meisten
 * Fehlschlaegen, ein Filter je Webhook. Das gehoert dorthin und bleibt dort.
 *
 * Was hier gemeldet wird, sind die vier Zahlen, die neben Umsatz und Newsletter
 * einen Sinn ergeben: kam etwas an, wie viel davon nicht, wie oft, wie schnell.
 * Wer danach wissen will, warum, klickt in den eigenen Schirm. Die Tiefe zweimal
 * zu bauen hiesse, zwei Auswertungen zu pflegen, die sich frueher oder spaeter
 * widersprechen — und bei einer Kennzahl ist ein Widerspruch nicht nur haesslich,
 * er macht beide Seiten unbrauchbar.
 *
 * **Der Zeitstempel ist `created_at`.** Eine Zustellung traegt drei: `created_at`
 * (das Ereignis ist passiert und muss raus), `first_attempted_at` (der erste
 * Versuch) und `last_attempted_at` (der letzte). Die Wahl faellt aus zwei
 * Gruenden auf den ersten:
 *
 * 1. **Ein vergangenes Fenster muss stillstehen.** `last_attempted_at` wandert
 *    mit jedem Wiederholungsversuch. Eine Zustellung, die ueber Nacht acht Mal
 *    versucht wird, waere gestern eine Zeile und heute keine mehr — und die Zahl
 *    von gestern aendert sich, nachdem jemand sie gelesen hat.
 * 2. **Ein Rueckstau muss sichtbar sein.** `first_attempted_at` ist `null`,
 *    solange nichts versucht wurde. Danach zu zaehlen hiesse, dass genau die
 *    Zustellungen fehlen, die haengen — und das ist die Sache, wegen der jemand
 *    auf diesen Schirm schaut.
 *
 * Der Preis ist benannt: eine Zustellung wird auf den Tag des Ereignisses
 * datiert, nicht auf den Tag, an dem sie tatsaechlich ankam. Bei einer Kette von
 * Wiederholungen ueber Mitternacht sind das verschiedene Tage. Dieselbe Wahl
 * trifft `DeliveryStatsService` fuer den eigenen Schirm, und zwei Schirme, die
 * denselben Tag verschieden schneiden, waeren schlimmer als der Rundungsfehler.
 *
 * **Alle vier Zahlen sind auf jetzt geklammert** und fragen deshalb ueber
 * {@see TableMetric::untilNow()} statt ueber `inPeriod()`. Beim Preset
 * „gesamter Zeitraum" hat das Fenster keine obere Grenze, und was dann in einer
 * Zeitspalte in der Zukunft steht, wird als Geschehenes gemeldet. Alle vier
 * beantworten, *was passiert ist*.
 *
 * Dass `created_at` heute nicht in der Zukunft liegen kann, ist kein Grund, es
 * wegzulassen: die Tabelle traegt mit `next_retry_at` bereits eine Spalte, in
 * der ausschliesslich Zukunft steht, und ein Fehler dieser Art zeigt sich nur
 * im weitesten Bereich, wo niemand die Zahl nachrechnet. Eine Kennzahl ueber
 * `next_retry_at` — „was steht noch aus" — wuerde umgekehrt **nicht** geklammert:
 * dort ist die Zukunft der Punkt.
 */
abstract class WebhookMetric extends TableMetric
{
    protected function table(): string
    {
        return 'webhook_deliveries';
    }

    protected function timestamp(): string
    {
        return 'created_at';
    }

    public function group(): string
    {
        return __('webhook-manager::insights.group');
    }

    /**
     * Die Spalte, an der `webhook_deliveries` ihre Marke traegt.
     *
     * Mehr braucht es nicht: {@see TableMetric::inPeriod()} verengt damit
     * Kachel, Verlauf und jede Aufteilung zugleich, nach genau den Regeln, nach
     * denen `BrandScope` jedes Modell dieses Addons verengt. Vorher stand hier
     * eine eigene Abschrift dieser Regeln; sie war richtig, aber sie war die
     * dritte von vieren in der Addon-Familie, und vier Abschriften sind vier
     * Gelegenheiten, spaeter auseinanderzulaufen.
     *
     * Im Einmarkenbetrieb filtert das nichts, wie drueben auch.
     */
    protected function brandColumn(): ?string
    {
        return 'brand_id';
    }

    /**
     * Ein Status in Worten, sonst der Status selbst.
     *
     * Uebersetzt wird nur, was dieses Addon besitzt: die fuenf Zustaende in
     * {@see Delivery}. Kommt
     * spaeter ein sechster dazu, erscheint sein Handle statt eines fehlenden
     * Schluessels — sichtbar und ohne dass diese Datei mitwachsen muss.
     */
    protected function statusLabel(string $status): string
    {
        $key = 'webhook-manager::insights.status.'.$status;
        $label = __($key);

        return is_string($label) && $label !== $key ? $label : $status;
    }

    protected function missingLabel(string $dimension): string
    {
        return __('webhook-manager::insights.no_'.$dimension);
    }

    /** Die Zustellungen im Fenster, die endgueltig gescheitert sind. */
    protected function failedInPeriod(MetricQuery $query): Builder
    {
        return $this->untilNow($query)->where('status', Delivery::STATUS_FAILED);
    }
}
