<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Tests\CpTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Jede Listen-Antwort traegt ihre Spalten mit.
 *
 * Statamics <Listing> holt die Zeilen per AJAX gegen dieselbe URL und macht mit
 * der Antwort als Erstes `setColumns(response.data.meta.columns)`. Fehlt der
 * Schluessel, stehen die Spalten auf `undefined`, der naechste Zugriff wirft —
 * und zwar im Erfolgspfad derselben Promise-Kette. Gefangen wird das vom
 * generischen `.catch` daneben, das nur „Something went wrong" toastet, weil
 * `e.response` fehlt. Ergebnis: HTTP 200, Tabelle sichtbar (die erste Fuellung
 * kommt aus den Inertia-Props), und trotzdem ein roter Fehler-Toast, der nicht
 * sagt, was los ist.
 *
 * Genau so gemeldet am 03.09.2026 von Adrian fuer `/inbound` und `/logs`
 * (Durchgang F32/F34). Betroffen waren sechs Listen, nicht zwei — der Test
 * geht deshalb ueber alle, damit eine neu hinzukommende nicht wieder einzeln
 * auffaellt.
 *
 * Der Test faehrt bewusst den JSON-Pfad (`getJson`), nicht den Inertia-Pfad:
 * `initialColumns` in den Props war die ganze Zeit gesetzt. Kaputt war nur die
 * Haelfte, die niemand ansieht.
 */
class ListingJsonCarriesColumnsTest extends CpTestCase
{
    use RefreshDatabase;

    public static function listingRoutes(): array
    {
        return [
            'inbound' => ['webhook-manager.inbound.index'],
            'outbound' => ['webhook-manager.outbound.index'],
            'logs' => ['webhook-manager.logs.index'],
            'deliveries' => ['webhook-manager.deliveries.index'],
            'rules' => ['webhook-manager.rules.index'],
            'templates' => ['webhook-manager.templates.index'],
        ];
    }

    #[DataProvider('listingRoutes')]
    public function test_the_listing_json_carries_its_columns(string $route): void
    {
        $meta = $this->actingAs($this->superUser())
            ->getJson(cp_route($route))
            ->assertOk()
            ->json('meta');

        $this->assertIsArray($meta, "$route liefert kein meta-Objekt");
        $this->assertArrayHasKey('columns', $meta, "$route liefert meta ohne columns — <Listing> laeuft damit auf undefined");
        $this->assertNotEmpty($meta['columns'], "$route liefert leere columns");

        // Die Form, die <Listing> liest: ein Feldname und eine Beschriftung je
        // Spalte. Ohne `field` sortiert und rendert die Tabelle ins Leere.
        foreach ($meta['columns'] as $i => $column) {
            $this->assertArrayHasKey('field', $column, "$route: Spalte $i ohne field");
            $this->assertArrayHasKey('label', $column, "$route: Spalte $i ohne label");
        }
    }
}
