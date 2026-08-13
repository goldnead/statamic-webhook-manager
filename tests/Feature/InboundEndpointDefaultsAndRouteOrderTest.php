<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\BrandContext\Models\Brand;
use Goldnead\WebhookManager\Auth\Support\SignatureGenerator;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Goldnead\WebhookManager\Http\Middleware\ResolveInboundBrand;
use Goldnead\WebhookManager\Tests\CpTestCase;
use Goldnead\WebhookManager\WebhookManagerServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/**
 * Two claims that a test on the layer below cannot make.
 *
 * The first is a default. Calling `CreateInboundEndpointAction` with the key
 * missing proves what the action does with a missing key — it does not prove
 * that anything ever calls it that way. The Control Panel is the only route by
 * which a human creates an endpoint, and it sends every field of its form, so
 * a default that lives only in the action never reaches a real endpoint.
 *
 * The second is that `{brand}` reaches `ResolveInboundBrand` as the raw string
 * the sender put in the URL. Nothing about a list of class names proves that;
 * asserting the list asserts the list. So the failure is provoked instead: a
 * `Route::bind` on the same parameter name, of the kind a sibling addon could
 * register without ever having heard of this one, and a delivery sent through
 * it. With `SubstituteBindings` still in the inbound stack this test fails —
 * which is how that middleware came out of it in 2.1.0.
 */
class InboundEndpointDefaultsAndRouteOrderTest extends CpTestCase
{
    use RefreshDatabase;

    /**
     * The form the CP renders for a new endpoint offers the guard ticked.
     *
     * This is the assertion that guards the fix, and the first attempt at it did
     * not: a POST body that leaves `replay_protection_enabled` out lets the
     * action's own default answer, so the test stayed green with the CP
     * template back on `false`. The template is what a human sees and submits,
     * so the template is what gets asserted.
     */
    public function test_the_create_form_offers_replay_protection_ticked(): void
    {
        $antwort = $this->createFormAnfragen();

        $antwort->assertOk();

        $this->assertTrue(
            (bool) $this->inertiaProp($antwort, 'endpoint.replay_protection_enabled'),
            'the create form decides what a human submits — the action default never sees this field',
        );
    }

    /**
     * And the value survives the round trip: nothing between the form and the
     * stored row turns it back off.
     */
    public function test_an_endpoint_created_through_the_control_panel_has_replay_protection_on(): void
    {
        $vorlage = (array) $this->inertiaProp($this->createFormAnfragen(), 'endpoint');

        // Exactly what the form posts back: the fields it was handed.
        $this->actingAs($this->superUser())
            ->post(cp_route('webhook-manager.inbound.store'), [
                'name' => 'Scaleway events',
                'handle' => 'scaleway-events',
                'path' => 'scaleway-events',
                'enabled' => $vorlage['enabled'],
                'allowed_methods' => $vorlage['allowed_methods'],
                'auth_type' => 'hmac',
                'auth_config_json' => json_encode(['secret' => 'shared-secret', 'algorithm' => 'sha256']),
                'expected_content_type' => $vorlage['expected_content_type'],
                'max_payload_kb' => $vorlage['max_payload_kb'],
                'replay_protection_enabled' => $vorlage['replay_protection_enabled'],
                'logging_mode' => $vorlage['logging_mode'],
                'action_type' => $vorlage['action_type'],
            ]);

        $endpoint = InboundEndpoint::query()->where('handle', 'scaleway-events')->firstOrFail();

        $this->assertTrue((bool) $endpoint->replay_protection_enabled);
    }

    /**
     * The create page, asked for as Inertia asks for it.
     *
     * With the `X-Inertia` header the response is the page object as JSON
     * rather than the host application's root view — which the package test bed
     * does not have, and which is not what is being asserted anyway.
     */
    private function createFormAnfragen(): TestResponse
    {
        return $this->actingAs($this->superUser())
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
            ->get(cp_route('webhook-manager.inbound.create'));
    }

    /** @return mixed */
    private function inertiaProp($antwort, string $pfad)
    {
        $seite = json_decode((string) $antwort->getContent(), true);
        $wert = $seite['props'] ?? [];

        foreach (explode('.', $pfad) as $teil) {
            $wert = is_array($wert) ? ($wert[$teil] ?? null) : null;
        }

        return $wert;
    }

    public function test_a_foreign_route_binding_on_brand_cannot_intercept_a_delivery(): void
    {
        config()->set('brand-context.multi_brand', true);
        config()->set('brand-context.license_check', null);
        app('brand-context')->forget();

        // Exactly what the addon's own rule warns about: a Route::bind reaches
        // into every route with that parameter name in every addon installed
        // alongside. This one aborts, which is what a binding that resolves a
        // model normally does when it finds nothing.
        Route::bind('brand', fn () => abort(404, 'foreign binding won'));

        $marke = Brand::query()->create([
            'handle' => 'brand-a',
            'name' => 'Brand A',
            'is_default' => false,
        ]);

        app('brand-context')->runFor($marke, fn () => InboundEndpoint::create([
            'name' => 'Events',
            'handle' => 'events',
            'enabled' => true,
            'path' => 'events',
            'allowed_methods' => ['POST'],
            'auth_type' => 'hmac',
            'auth_config' => ['secret' => 'shared-secret', 'algorithm' => 'sha256'],
            'expected_content_type' => 'application/json',
            'action_type' => 'noop',
        ]));

        $body = json_encode(['hello' => 'world']);

        app('brand-context')->forget();

        $this->call('POST', '/webhooks/inbound/brand-a/events', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => SignatureGenerator::compute($body, 'shared-secret', 'sha256'),
        ], $body)->assertStatus(200);
    }

    public function test_the_brand_resolver_is_the_first_middleware_declared(): void
    {
        $stack = WebhookManagerServiceProvider::inboundMiddleware();

        $this->assertSame(ResolveInboundBrand::class, $stack[0] ?? null);
    }

    /**
     * The listing hands the browser a finished URL.
     *
     * `Index.vue` prints `endpoint.public_path` and composes nothing of its
     * own any more, which is the point — but it also means a missing key is a
     * blank URL next to a copy button rather than an error anyone would notice.
     */
    public function test_the_listing_payload_carries_the_public_url(): void
    {
        InboundEndpoint::create([
            'name' => 'Scaleway events',
            'handle' => 'scaleway-events',
            'enabled' => true,
            'path' => 'ein-anderer-pfad-als-der-handle',
            'allowed_methods' => ['POST'],
            'auth_type' => 'hmac',
            'auth_config' => ['secret' => 'shared-secret'],
            'action_type' => 'noop',
        ]);

        $antwort = $this->actingAs($this->superUser())
            ->getJson(cp_route('webhook-manager.inbound.index'));

        $antwort->assertOk();

        $zeile = $antwort->json('data.0');

        $this->assertNotNull($zeile, 'the listing is expected to return the endpoint');
        $this->assertSame(
            WebhookManagerServiceProvider::inboundPath('scaleway-events'),
            $zeile['public_path'] ?? null,
        );

        // And it is built from the handle, not from `path` — that split is how
        // the CP used to print a URL the router never matched.
        $this->assertStringEndsWith('/scaleway-events', (string) ($zeile['public_path'] ?? ''));
        $this->assertStringNotContainsString('ein-anderer-pfad-als-der-handle', (string) ($zeile['public_path'] ?? ''));
    }

    /**
     * A brand handle the router cannot match still appears in the URL, and is
     * reported as unroutable rather than quietly dropped.
     *
     * Dropping it produced `{prefix}/{handle}` — not a broken URL at all: it
     * routes, to the **default** brand. The operator would have been handed a
     * working link pointing at the wrong tenant with nothing to notice. Nothing
     * stops such a handle from existing; `Brand` is unguarded and its column
     * carries only a unique index.
     */
    public function test_an_unroutable_brand_handle_is_reported_not_hidden(): void
    {
        $this->assertSame(
            '/webhooks/inbound/chorgesucht/events',
            WebhookManagerServiceProvider::inboundPath('events', 'chorgesucht'),
        );
        $this->assertTrue(WebhookManagerServiceProvider::inboundPathIsRoutable('chorgesucht'));

        $this->assertSame(
            '/webhooks/inbound/Chor.de/events',
            WebhookManagerServiceProvider::inboundPath('events', 'Chor.de'),
            'the segment stays visible; a shortened URL would silently resolve to the default brand',
        );
        $this->assertFalse(WebhookManagerServiceProvider::inboundPathIsRoutable('Chor.de'));
    }
}
