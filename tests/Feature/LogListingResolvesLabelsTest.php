<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Domain\Log\Models\LogEntry;
use Goldnead\WebhookManager\Tests\CpTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * The log listing shows labels, not the values of two database columns.
 *
 * It printed `inbound_received`, `delivery_failed` and `inbound_auth_failed` in
 * the type badge and a bare `info` / `warning` next to it. Two causes, and the
 * second is the interesting one:
 *
 *  1. The Vue page kept its own English map instead of using the label the
 *     controller already put on the row — the second copy of a decision, the
 *     same shape as the one removed from the delivery listing.
 *  2. That label never resolved anyway. The controller looked the type up in
 *     `failure_types.*`, the eight classes of a failed DELIVERY, while this
 *     column holds a log EVENT type. Not one value ever matched, so every
 *     lookup fell through to the raw handle — quietly, because the fallback is
 *     supposed to be the rare case.
 *
 * Removing the Vue copy alone would have fixed nothing: the raw handle would
 * simply have arrived from the server instead. So this test asserts the label
 * differs from the handle, which is what both defects have in common.
 *
 * Sibling of InboundListingResolvesLabelsTest and
 * DeliveryListingResolvesStatusTest.
 */
class LogListingResolvesLabelsTest extends CpTestCase
{
    use RefreshDatabase;

    public function test_the_listing_resolves_the_event_type_and_the_level(): void
    {
        $this->makeEntry('warning', 'inbound_auth_failed');

        $row = $this->firstRow();

        $this->assertSame('inbound_auth_failed', $row['error_type'], 'The raw value must stay available.');
        $this->assertNotSame('inbound_auth_failed', $row['error_type_label'], 'The listing printed the raw handle.');
        $this->assertSame(
            __('webhook-manager::messages.cp.log_types.inbound_auth_failed'),
            $row['error_type_label'],
        );

        $this->assertSame('warning', $row['level']);
        $this->assertSame(__('webhook-manager::messages.cp.log_levels.warning'), $row['level_label']);
        $this->assertSame('amber', $row['level_color']);
    }

    /**
     * The colours are grouped by what happened, not listed per handle, so a
     * newly logged event gets a sensible one without an edit. A success must
     * not come out in the colour of a refusal.
     */
    public function test_the_event_type_colour_follows_what_happened(): void
    {
        foreach ([
            'delivery_success' => 'green',
            'inbound_received' => 'blue',
            'delivery_failed' => 'red',
            'inbound_rate_limited' => 'amber',
        ] as $type => $expected) {
            LogEntry::query()->delete();
            $this->makeEntry('info', $type);

            $this->assertSame($expected, $this->firstRow()['error_type_color'], "colour for $type");
        }
    }

    /**
     * An event type no language file knows must fall back to the raw handle —
     * never to the translation key, which is what a reader saw on the insights
     * panel, and never to a blank.
     */
    public function test_an_unknown_event_type_falls_back_to_the_raw_handle(): void
    {
        $this->makeEntry('info', 'quarantined_by_a_future_version');

        $this->assertSame('quarantined_by_a_future_version', $this->firstRow()['error_type_label']);
    }

    private function makeEntry(string $level, string $type): void
    {
        LogEntry::create([
            'uuid' => (string) Str::uuid(),
            'level' => $level,
            'type' => $type,
            'message' => 'Something was logged.',
            'correlation_id' => 'corr-'.$type,
        ]);
    }

    private function firstRow(): array
    {
        return $this->actingAs($this->superUser())
            ->withHeaders($this->inertiaHeaders())
            ->get(cp_route('webhook-manager.logs.index'))
            ->assertOk()
            ->json('props.logs.data.0');
    }
}
