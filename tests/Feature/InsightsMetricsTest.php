<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\BrandContext\Facades\BrandContext;
use Goldnead\StatamicInsights\Contracts\Metric;
use Goldnead\StatamicInsights\Facades\Insights as InsightsStandIn;
use Goldnead\StatamicInsights\Support\MetricQuery;
use Goldnead\StatamicInsights\Support\Period;
use Goldnead\StatamicInsights\Support\TableMetric;
use Goldnead\StatamicInsights\Support\Unit;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Integrations\Insights\Deliveries;
use Goldnead\WebhookManager\Integrations\Insights\Failures;
use Goldnead\WebhookManager\Integrations\Insights\LatencyAvg;
use Goldnead\WebhookManager\Integrations\Insights\SuccessRate;
use Goldnead\WebhookManager\Services\DeliveryStatsService;
use Goldnead\WebhookManager\Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Die vier Kernzahlen, die dieses Addon dem Analytics-Addon anbietet.
 *
 * Vier und nicht vierzehn, mit Absicht: Perzentile, Fehlerklassen und die
 * Endpunkte mit den meisten Fehlschlaegen bleiben im eigenen Schirm des Addons
 * ({@see DeliveryStatsService}), und
 * `DeliveryStatsServiceTest` prueft sie dort. Was hier geprueft wird, ist
 * ausschliesslich, dass die vier Zahlen auf dem gemeinsamen Brett dasselbe
 * sagen wie die Tabelle.
 *
 * Jede Erwartung ist von Hand aus derselben kleinen Vorlage gerechnet. Gegen
 * einen Stellvertreter des Vertrags getestet und nicht gegen das echte Paket,
 * aus demselben Grund, aus dem das Geschwister ein `suggest` ist: ein Test, der
 * es installiert braeuchte, wuerde das Gegenteil dessen belegen, was dieses
 * Addon behauptet.
 *
 * Die Zeit ist eingefroren, weil die Eimer als konkrete Daten geprueft werden.
 */
class InsightsMetricsTest extends TestCase
{
    protected const HEUTE = '2026-08-20 12:00:00';

    /** Sammelt ein, was der ServiceProvider registriert. */
    protected object $insights;

    protected function setUp(): void
    {
        // Vor der Anwendung, alle drei. Die Vertraege muessen da sein, bevor
        // eine Kennzahl-Klasse geladen wird, und die Fassade, bevor der
        // Provider in seinem `booted()`-Rueckruf fragt, ob es sie gibt.
        //
        // Die Basisklasse liegt als eigene Datei daneben und traegt keine
        // Absicherung im Kopf: sie ist eine Byte-fuer-Byte-Kopie, und die
        // Absicherung sitzt deshalb hier. Siehe InsightsContractsMatchTest.
        require_once __DIR__.'/../Fakes/insights-contracts.php';

        if (! class_exists(TableMetric::class, false)) {
            require_once __DIR__.'/../Fakes/insights-table-metric.php';
        }

        require_once __DIR__.'/../Fakes/insights-facade.php';

        $this->insights = new class
        {
            /** @var array<string, string> */
            public array $registered = [];

            /**
             * Strenger als die echte Verwaltung, mit Absicht: die nimmt eine
             * Kennzahl auch ohne Handle an und ermittelt ihn, indem sie sie
             * baut. Das hier anzunehmen hiesse, dass der Provider den Handle
             * weglassen koennte und trotzdem richtig aussieht — und der Handle
             * ist die Haelfte, die in gespeicherten Ansichten und URLs landet.
             */
            public function registerMetric(string|Metric|\Closure $metric, ?string $handle = null): void
            {
                if (! is_string($metric) || $handle === null) {
                    throw new \InvalidArgumentException('This addon registers metrics lazily: a class name and a handle.');
                }

                $this->registered[$handle] = $metric;
            }
        };

        InsightsStandIn::$root = $this->insights;

        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::HEUTE));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        InsightsStandIn::$root = null;

        parent::tearDown();
    }

    // -- Die Vorlage --------------------------------------------------------

    /**
     * Sechs Zustellungen, davon fuenf im Fenster.
     *
     * Klein genug, um sie im Kopf zu addieren, und jeder Fall, der eine Zahl
     * kippen kann, ist drin: eine, die auf einen Wiederholungsversuch wartet
     * (kein Urteil, keine Messung), eine abgebrochene (dito), eine gescheiterte
     * und eine ausserhalb des Fensters.
     */
    protected function fixture(): void
    {
        $this->delivery(['status' => Delivery::STATUS_SUCCESS, 'created_at' => '2026-08-15 10:00:00', 'duration_ms' => 200]);
        $this->delivery(['status' => Delivery::STATUS_SUCCESS, 'created_at' => '2026-08-15 12:00:00', 'duration_ms' => 400]);
        $this->delivery(['status' => Delivery::STATUS_FAILED, 'created_at' => '2026-08-18 09:00:00', 'duration_ms' => 1200]);

        // Wartet auf einen Wiederholungsversuch: noch kein Urteil, noch keine
        // gemessene Antwortzeit.
        $this->delivery(['status' => Delivery::STATUS_PENDING, 'created_at' => '2026-08-19 08:00:00', 'duration_ms' => null]);

        // Jemand hat sie angehalten. Kein Fehlschlag der Gegenstelle.
        $this->delivery(['status' => Delivery::STATUS_CANCELLED, 'created_at' => '2026-08-19 09:00:00', 'duration_ms' => null]);

        // Vor dem Fenster.
        $this->delivery(['status' => Delivery::STATUS_SUCCESS, 'created_at' => '2026-07-01 09:00:00', 'duration_ms' => 9000]);
    }

    /** @param  array<string, mixed>  $overrides */
    protected function delivery(array $overrides = []): Delivery
    {
        return Delivery::create(array_merge([
            'trigger_type' => 'entry.saved',
            'status' => Delivery::STATUS_SUCCESS,
            'request_url' => 'https://receiver.example.test/hook',
            'request_method' => 'POST',
            'response_status' => 200,
            'attempts' => 1,
            'duration_ms' => 200,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /** Die zehn Tage, in denen die Vorlage lebt, nach Tagen gebucketet. */
    protected function frage(string $bucket = MetricQuery::BUCKET_DAY): MetricQuery
    {
        return new MetricQuery(
            Period::between(Carbon::parse('2026-08-11')->startOfDay(), Carbon::parse('2026-08-20')->endOfDay()),
            $bucket,
        );
    }

    /** Ein leeres Fenster: alles davor. */
    protected function stillesFenster(): MetricQuery
    {
        return new MetricQuery(
            Period::between(Carbon::parse('2026-08-01')->startOfDay(), Carbon::parse('2026-08-05')->endOfDay()),
        );
    }

    /**
     * @param  array<int, array{key: string|null, label: string, value: int|float}>  $rows
     * @return array<string, int|float>
     */
    protected function keyed(array $rows): array
    {
        $keyed = [];

        foreach ($rows as $row) {
            $keyed[$row['key'] ?? ''] = $row['value'];
        }

        return $keyed;
    }

    // -- Die vier Zahlen ----------------------------------------------------

    /**
     * Alle vier auf einmal, gegen von Hand gerechnete Summen.
     *
     * Ein Test statt vier, weil sie auf einem Schirm nebeneinander stehen und
     * zueinander passen muessen. Eine Zustellzahl, die sich bewegt hat, ohne
     * dass die Quote folgt, ist der Fehlschlag, der sich zu fangen lohnt.
     */
    public function test_the_four_figures_match_what_the_deliveries_table_says(): void
    {
        $this->fixture();
        $frage = $this->frage();

        $this->assertSame(5, (new Deliveries)->value($frage), 'fuenf Zustellungen im Fenster, eine davor');
        $this->assertSame(1, (new Failures)->value($frage), 'nur die gescheiterte, nicht die wartende oder abgebrochene');

        // 2 von 3 mit einem Urteil. Die wartende und die abgebrochene haben
        // keines und stehen in keinem der beiden Teile.
        $this->assertSame(66.7, (new SuccessRate)->value($frage), 'round(2 / 3 * 100, 1)');

        // (200 + 400 + 1200) / 3 = 600 ms. Die beiden ohne Messung sind keine
        // Nullen: mit ihnen waeren es 360 ms, und ein Rueckstau wuerde die
        // Antwortzeit genau dann verkuerzen, wenn nichts antwortet.
        $this->assertSame(0.6, (new LatencyAvg)->value($frage), '600 ms in Sekunden');
    }

    /**
     * Millisekunden werden zu Sekunden, und eine halbe bleibt eine halbe.
     *
     * Auf ganze Sekunden abzuschneiden waere fuer jede gesunde Installation
     * eine Null, denn eine Zustellung, die gut laeuft, dauert zwei- bis
     * dreihundert Millisekunden. Die genaue Zahl steht zusaetzlich im `meta`,
     * damit die Formatierung auf ganze Sekunden sie nicht verschluckt.
     */
    public function test_milliseconds_become_seconds_and_the_exact_figure_survives_in_meta(): void
    {
        $this->fixture();
        $frage = $this->frage();

        $this->assertSame(0.6, (new LatencyAvg)->value($frage));
        $this->assertSame(['duration_ms' => 600.0], (new LatencyAvg)->meta($frage));

        $this->assertSame(
            ['2026-08-15' => 0.3, '2026-08-18' => 1.2],
            (new LatencyAvg)->series($frage),
            'der 19. hat keine gemessene Zustellung und damit keinen Eimer',
        );
    }

    /** Die Handles sind ein Versprechen. Sie landen in Ansichten und URLs. */
    public function test_the_handles_units_and_group_are_the_ones_that_were_promised(): void
    {
        $erwartet = [
            [Deliveries::class, 'webhooks.deliveries', Unit::COUNT],
            [Failures::class, 'webhooks.failures', Unit::COUNT],
            [SuccessRate::class, 'webhooks.success_rate', Unit::PERCENT],
            [LatencyAvg::class, 'webhooks.latency_avg', Unit::DURATION],
        ];

        foreach ($erwartet as [$klasse, $handle, $unit]) {
            $metrik = new $klasse;

            $this->assertSame($handle, $metrik->handle());
            $this->assertSame($unit, $metrik->unit());
            $this->assertSame(__('webhook-manager::insights.group'), $metrik->group());
            $this->assertNotSame('', $metrik->label());
            $this->assertNotEmpty($metrik->description());
        }
    }

    /** Der Provider bietet genau diese vier an, faul und mit Handle. */
    public function test_the_provider_offers_every_figure_to_the_sibling(): void
    {
        $this->assertSame([
            'webhooks.deliveries' => Deliveries::class,
            'webhooks.failures' => Failures::class,
            'webhooks.success_rate' => SuccessRate::class,
            'webhooks.latency_avg' => LatencyAvg::class,
        ], $this->insights->registered);
    }

    /**
     * Eine Zustellung mit einem Datum in der Zukunft ist keine Geschichte.
     *
     * „Gesamter Zeitraum" hat keine obere Grenze. Ohne die Klammer ueber
     * `untilNow()` meldete die Kachel dort alles, was in einer Zeitspalte
     * vorwaerts zeigt, als waere es geschehen — und nur dort, wo niemand
     * nachrechnet. `created_at` traegt das heute nicht, `next_retry_at` in
     * derselben Tabelle traegt ausschliesslich das.
     */
    public function test_a_delivery_dated_in_the_future_is_not_history(): void
    {
        $this->fixture();

        $this->delivery(['status' => Delivery::STATUS_SUCCESS, 'created_at' => '2027-01-04 09:00:00', 'duration_ms' => 50]);

        $alleZeit = new MetricQuery(Period::fromPreset('all'), MetricQuery::BUCKET_MONTH);

        $this->assertSame(6, (new Deliveries)->value($alleZeit), 'die fuenf im Fenster plus die vom Juli');
        $this->assertArrayNotHasKey('2027-01', (new Deliveries)->series($alleZeit));
    }

    /**
     * Die Zeitzone der Anwendung verschiebt die Fenstergrenze nicht.
     *
     * Insights baut seinen Zeitraum aus `Carbon::now()`, also aus der Zeit der
     * Anwendung. Dieses Addon schreibt seine Zeitstempel durch Eloquent,
     * ebenfalls in Anwendungszeit — beide Seiten sind naiv lokal. Ein Addon,
     * das UTC schriebe, waere auf einer Installation in Chicago um fuenf
     * Stunden versetzt, und der Fehler zeigte sich nur an den Raendern: eine
     * Zustellung um 23:30 fiele aus dem Tag heraus.
     */
    public function test_the_window_holds_under_a_non_utc_application_timezone(): void
    {
        $vorher = date_default_timezone_get();

        config()->set('app.timezone', 'America/Chicago');
        date_default_timezone_set('America/Chicago');

        try {
            Carbon::setTestNow(Carbon::parse('2026-08-20 23:30:00'));

            $this->delivery(['status' => Delivery::STATUS_SUCCESS, 'created_at' => now(), 'duration_ms' => 200]);

            $frage = new MetricQuery(Period::fromPreset('7d'), MetricQuery::BUCKET_DAY);

            $this->assertSame(1, (new Deliveries)->value($frage), 'eine Zustellung um 23:30 gehoert in den heutigen Tag');
            $this->assertSame(['2026-08-20' => 1], (new Deliveries)->series($frage));
        } finally {
            date_default_timezone_set($vorher);
            config()->set('app.timezone', $vorher);
        }
    }

    // -- Nichts zu messen ---------------------------------------------------

    /**
     * Keine Tabelle, keine Antwort — und keine Null.
     *
     * „Nichts zu messen" und „nichts gemessen" sind verschiedene Aussagen, und
     * eine Null fuer die erste setzt eine selbstbewusste 0 auf den Schirm einer
     * Installation, die dieses Addon gar nicht migriert hat.
     */
    public function test_a_metric_cannot_answer_without_its_table(): void
    {
        $this->assertTrue((new Deliveries)->available());

        // Eine zweite, leere Datenbank statt eines Drops in dieser: ein Drop
        // liesse die Suite ihre eigenen Migrationen nicht mehr zuruecknehmen.
        config()->set('database.connections.ohne_zustellungen', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $vorher = DB::getDefaultConnection();
        DB::purge('ohne_zustellungen');
        DB::setDefaultConnection('ohne_zustellungen');

        try {
            foreach ([Deliveries::class, Failures::class, SuccessRate::class, LatencyAvg::class] as $klasse) {
                $metrik = new $klasse;

                $this->assertFalse($metrik->available(), $klasse.' antwortete ohne seine Tabelle.');
                $this->assertNull($metrik->value($this->frage()), $klasse.' lieferte einen Wert ohne seine Tabelle.');
                $this->assertSame([], $metrik->series($this->frage()));
            }

            $this->assertSame(['duration_ms' => null], (new LatencyAvg)->meta($this->frage()));
        } finally {
            DB::setDefaultConnection($vorher);
        }
    }

    // -- Quoten ohne Nenner -------------------------------------------------

    /**
     * Eine Quote ohne Nenner ist `null`, nie null Prozent.
     *
     * Der eigene Schirm dieses Addons liefert dort heute `0.0`. Auf einem
     * gemeinsamen Brett ist das die falsche Antwort: „0 %" stuende neben einer
     * Zustellzahl von null und behauptete, es sei etwas schiefgegangen.
     */
    public function test_a_rate_without_a_denominator_has_no_answer(): void
    {
        $this->fixture();

        $this->assertNull((new SuccessRate)->value($this->stillesFenster()));
        $this->assertNull((new LatencyAvg)->value($this->stillesFenster()));
        $this->assertSame(0, (new Deliveries)->value($this->stillesFenster()), 'gezaehlt wird trotzdem, und zwar null');
    }

    /**
     * Auch ein Fenster voller wartender Zustellungen hat keine Quote.
     *
     * Der Fall, der die Wahl des Nenners traegt: alles steht in der
     * Warteschlange. `erfolgreich / alle` waere hier 0 % und behauptete einen
     * Ausfall, wo bisher nur nichts entschieden ist.
     */
    public function test_deliveries_that_are_all_still_pending_produce_no_rate(): void
    {
        $this->delivery(['status' => Delivery::STATUS_PENDING, 'created_at' => '2026-08-14 10:00:00', 'duration_ms' => null]);
        $this->delivery(['status' => Delivery::STATUS_PENDING, 'created_at' => '2026-08-14 11:00:00', 'duration_ms' => null]);

        $this->assertSame(2, (new Deliveries)->value($this->frage()));
        $this->assertNull((new SuccessRate)->value($this->frage()));
    }

    /**
     * Ein Eimer ohne Nenner bleibt `null` und wird nicht ausgelassen.
     *
     * Ausgelassene Eimer fuellt das Analytics-Addon mit einer Null auf, und
     * eine Null ist bei einer Quote die Behauptung „an diesem Tag kam nichts
     * an". Am 19.08. wartet eine Zustellung und eine ist abgebrochen — kein
     * Urteil, also keine Saeule statt einer leeren.
     */
    public function test_a_bucket_without_a_denominator_stays_null(): void
    {
        $this->fixture();

        $this->assertSame(
            ['2026-08-15' => 100.0, '2026-08-18' => 0.0, '2026-08-19' => null],
            (new SuccessRate)->series($this->frage()),
        );
    }

    // -- Die eine Aufteilung ------------------------------------------------

    /** Nach Status, mit Worten statt Handles, und sonst nichts. */
    public function test_the_only_split_is_by_status_and_it_carries_words(): void
    {
        $this->fixture();
        $frage = $this->frage();

        $nachStatus = $this->keyed((new Deliveries)->breakdown($frage, 'status'));
        ksort($nachStatus);

        $this->assertSame([
            Delivery::STATUS_CANCELLED => 1,
            Delivery::STATUS_FAILED => 1,
            Delivery::STATUS_PENDING => 1,
            Delivery::STATUS_SUCCESS => 2,
        ], $nachStatus);

        $zeilen = (new Deliveries)->breakdown($frage, 'status');

        $this->assertSame(Delivery::STATUS_SUCCESS, $zeilen[0]['key'], 'groesste zuerst');
        $this->assertSame(__('webhook-manager::insights.status.success'), $zeilen[0]['label']);
        $this->assertSame(5, array_sum(array_column($zeilen, 'value')), 'die Aufteilung addiert sich zur Zustellzahl');

        $this->assertSame(['status'], array_keys((new Deliveries)->breakdowns()));

        // Fehlerklassen und Endpunkte bleiben im eigenen Schirm des Addons.
        $this->assertSame([], (new Deliveries)->breakdown($frage, 'error_type'));
        $this->assertSame([], (new Deliveries)->breakdown($frage, 'webhook'));
    }

    // -- Eine Marke sieht ihre eigenen Zahlen -------------------------------

    /**
     * Im Mehrmarkenbetrieb zaehlt eine Kachel nur die Marke, die gerade gilt.
     *
     * `TableMetric` liest ueber den Query-Builder, an Eloquent und damit an
     * `BrandScope` vorbei. Ohne die nachgebaute Absicherung summierte die
     * Kachel ueber alle Marken, waehrend die Zustellliste daneben eine zeigt.
     */
    public function test_a_figure_counts_only_the_brand_that_is_current(): void
    {
        config()->set('brand-context.multi_brand', true);
        app('brand-context')->forget();

        $a = DB::table('brands')->insertGetId([
            'handle' => 'marke-a', 'name' => 'Marke A', 'is_default' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $b = DB::table('brands')->insertGetId([
            'handle' => 'marke-b', 'name' => 'Marke B', 'is_default' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        BrandContext::runFor($a, fn () => $this->delivery([
            'status' => Delivery::STATUS_SUCCESS,
            'created_at' => '2026-08-14 10:00:00',
            'duration_ms' => 100,
        ]));

        BrandContext::runFor($b, function () {
            $this->delivery(['status' => Delivery::STATUS_SUCCESS, 'created_at' => '2026-08-14 11:00:00', 'duration_ms' => 500]);
            $this->delivery(['status' => Delivery::STATUS_FAILED, 'created_at' => '2026-08-14 12:00:00', 'duration_ms' => 500]);
        });

        BrandContext::setCurrent($a);
        $this->assertSame(1, (new Deliveries)->value($this->frage()));
        $this->assertSame(0, (new Failures)->value($this->frage()));
        $this->assertSame(100.0, (new SuccessRate)->value($this->frage()));
        $this->assertSame(0.1, (new LatencyAvg)->value($this->frage()));

        BrandContext::setCurrent($b);
        $this->assertSame(2, (new Deliveries)->value($this->frage()));
        $this->assertSame(1, (new Failures)->value($this->frage()));
        $this->assertSame(50.0, (new SuccessRate)->value($this->frage()));
    }

    /**
     * Ohne aufgeloeste Marke wird die Kachel zu einer Null, nicht zu einer
     * Luecke.
     *
     * `available()` beantwortet, ob es die Sache gibt — steht die Tabelle, ist
     * das Geschwister installiert. Eine Marke, die niemand gewaehlt hat, ist
     * nichts davon. Die Zeilen werden weiterhin verweigert (`fail closed`),
     * also steht auf dem Schirm eine Null statt einer Summe ueber alle Marken;
     * eine verschwundene Kachel dagegen bemerkt niemand.
     */
    public function test_an_unresolved_brand_reads_nought_and_stays_on_the_screen(): void
    {
        $this->fixture();

        config()->set('brand-context.multi_brand', true);
        app('brand-context')->forget();
        BrandContext::setCurrent(null);

        foreach ([new Deliveries, new Failures, new SuccessRate, new LatencyAvg] as $kennzahl) {
            $this->assertTrue(
                $kennzahl->available(),
                $kennzahl->handle().' ist von der Marke abhaengig geworden, statt von seiner Tabelle',
            );
        }

        $this->assertSame(0, (new Deliveries)->value($this->frage()));
        $this->assertSame(0, (new Failures)->value($this->frage()));
        $this->assertSame([], (new Deliveries)->series($this->frage()));

        // Ohne Zeilen hat eine Quote und ein Durchschnitt weiterhin keine
        // Antwort. Das ist eine Aussage ueber den Nenner, keine ueber die Marke.
        $this->assertNull((new SuccessRate)->value($this->frage()));
        $this->assertNull((new LatencyAvg)->value($this->frage()));

        // Wo die Installation die andere Antwort vorzieht, liest die Kachel
        // ueber die Marken hinweg — wie `BrandScope` mit `fail_mode: open`.
        config()->set('brand-context.fail_mode', 'open');
        app('brand-context')->forget();
        BrandContext::setCurrent(null);

        $this->assertSame(5, (new Deliveries)->value($this->frage()));
    }
}
