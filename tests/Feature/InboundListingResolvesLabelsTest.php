<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Goldnead\WebhookManager\Tests\CpTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The inbound listing shows labels, not the column values.
 *
 * The Auth column printed `static_header` and `bearer` — the handles the
 * database stores and the auth registry keys itself by. That is the addon's
 * vocabulary, not the operator's, and it was printed at a reader in a badge
 * next to an Action column that had already resolved its label properly.
 *
 * Both labels are resolved here, server-side, where the registries and the
 * translation table are, and they are part of the row payload so the listing's
 * own AJAX refresh (search, sort, page) carries them too — a label handed to
 * the page as a separate prop would survive the first render and then not the
 * second.
 */
class InboundListingResolvesLabelsTest extends CpTestCase
{
    use RefreshDatabase;

    public function test_the_listing_resolves_the_auth_and_action_labels(): void
    {
        InboundEndpoint::create([
            'name' => 'Receiver',
            'handle' => 'receiver',
            'path' => 'receiver',
            'enabled' => true,
            'auth_type' => 'static_header',
            'auth_config' => ['header' => 'X-Token', 'value' => 'secret'],
            'allowed_methods' => ['POST'],
            'action_type' => 'noop',
        ]);

        $row = $this->actingAs($this->superUser())
            ->withHeaders($this->inertiaHeaders())
            ->get(cp_route('webhook-manager.inbound.index'))
            ->assertOk()
            ->json('props.endpoints.data.0');

        $this->assertSame('static_header', $row['auth_type']);
        $this->assertNotSame('static_header', $row['auth_type_label']);
        $this->assertNotEmpty($row['auth_type_label']);

        $this->assertSame('noop', $row['action_type']);
        $this->assertNotSame('noop', $row['action_type_label']);
        $this->assertNotEmpty($row['action_type_label']);
    }

    public function test_an_unknown_handle_falls_back_to_a_sentence_rather_than_to_the_handle(): void
    {
        // A scheme removed by an addon upgrade, or a row written by a version
        // that knew a handler this one does not. Printing the handle is the
        // failure mode this test exists to prevent: the operator is shown a
        // word from the schema and cannot tell it apart from a real label.
        InboundEndpoint::create([
            'name' => 'Orphan',
            'handle' => 'orphan',
            'path' => 'orphan',
            'enabled' => true,
            'auth_type' => 'scheme_from_the_future',
            'allowed_methods' => ['POST'],
            'action_type' => 'handler_from_the_future',
        ]);

        $row = $this->actingAs($this->superUser())
            ->withHeaders($this->inertiaHeaders())
            ->get(cp_route('webhook-manager.inbound.index'))
            ->assertOk()
            ->json('props.endpoints.data.0');

        $this->assertStringNotContainsString('scheme_from_the_future', $row['auth_type_label']);
        $this->assertStringNotContainsString('handler_from_the_future', $row['action_type_label']);
        $this->assertSame(__('webhook-manager::messages.unknown_option'), $row['auth_type_label']);
        $this->assertSame(__('webhook-manager::messages.unknown_option'), $row['action_type_label']);
    }
}
