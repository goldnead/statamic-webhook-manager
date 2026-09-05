<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Tests\CpTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * The deliveries listing shows a status, not a database enum.
 *
 * The Status column printed `failed` and `success` — the values the column
 * stores — while the detail page one click away, and the overview two clicks
 * away, both said „Fehlgeschlagen". Same record, three vocabularies, and the
 * one the operator meets first was neither German nor a sentence.
 *
 * The label is resolved server-side, in the same trait that owns the colour,
 * and it rides along on the row rather than being handed to the page as a
 * separate prop — otherwise it survives the first render and not the listing's
 * own AJAX refresh on search, sort or page.
 *
 * Sibling of InboundListingResolvesLabelsTest, one screen along.
 */
class DeliveryListingResolvesStatusTest extends CpTestCase
{
    use RefreshDatabase;

    public function test_the_listing_resolves_the_status_label_and_colour(): void
    {
        $this->makeDelivery('failed', 'auth');

        $row = $this->firstRow();

        $this->assertSame('failed', $row['status'], 'The raw value must stay available.');
        $this->assertNotSame('failed', $row['status_label'], 'The listing printed the raw enum.');
        $this->assertNotEmpty($row['status_label']);
        $this->assertSame(__('webhook-manager::messages.cp.delivery_status.failed'), $row['status_label']);
        $this->assertSame('red', $row['status_color']);
    }

    public function test_the_listing_resolves_the_error_type_the_same_way(): void
    {
        // The twin defect one field along: the error type was mapped to an
        // English word in the Vue page ("Config") while PHP already had the
        // translated one ("Konfigurationsfehler").
        $this->makeDelivery('failed', 'configuration');

        $row = $this->firstRow();

        $this->assertSame('configuration', $row['error_type']);
        $this->assertSame(
            __('webhook-manager::messages.failure_types.configuration'),
            $row['error_type_label'],
        );
        $this->assertSame('blue', $row['error_type_color']);
    }

    /**
     * A status the translation table does not know must fall back to the raw
     * handle. Never to a blank, and never to "Unknown" — both hide which
     * status the record actually carries, which is the one thing the operator
     * came for.
     */
    public function test_an_unknown_status_falls_back_to_the_raw_handle(): void
    {
        $this->makeDelivery('quarantined', null);

        $this->assertSame('quarantined', $this->firstRow()['status_label']);
    }

    private function makeDelivery(string $status, ?string $errorType): void
    {
        Delivery::create([
            'uuid' => (string) Str::uuid(),
            'trigger_type' => 'entry.saved',
            'request_url' => 'https://example.test/hooks/demo',
            'request_method' => 'POST',
            'status' => $status,
            'error_type' => $errorType,
            'attempts' => 1,
        ]);
    }

    private function firstRow(): array
    {
        return $this->actingAs($this->superUser())
            ->withHeaders($this->inertiaHeaders())
            ->get(cp_route('webhook-manager.deliveries.index'))
            ->assertOk()
            ->json('props.deliveries.data.0');
    }
}
