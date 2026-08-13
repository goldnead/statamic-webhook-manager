<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\BrandContext\Models\Brand;
use Goldnead\WebhookManager\Auth\Support\SignatureGenerator;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Goldnead\WebhookManager\Http\Controllers\Cp\InboundController;
use Goldnead\WebhookManager\Http\Middleware\ResolveInboundBrand;
use Goldnead\WebhookManager\Tests\CpTestCase;
use Goldnead\WebhookManager\WebhookManagerServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

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

    public function test_an_endpoint_created_through_the_control_panel_has_replay_protection_on(): void
    {
        $this->actingAs($this->superUser())
            ->post(cp_route('webhook-manager.inbound.store'), [
                'name' => 'Scaleway events',
                'handle' => 'scaleway-events',
                'path' => 'scaleway-events',
                'enabled' => true,
                'allowed_methods' => ['POST'],
                'auth_type' => 'hmac',
                'auth_config_json' => json_encode(['secret' => 'shared-secret', 'algorithm' => 'sha256']),
                'expected_content_type' => 'application/json',
                'max_payload_kb' => 512,
                'logging_mode' => 'partial',
                'action_type' => 'noop',
            ]);

        $endpoint = InboundEndpoint::query()->where('handle', 'scaleway-events')->firstOrFail();

        $this->assertTrue(
            (bool) $endpoint->replay_protection_enabled,
            'the form template decides this, not the action default',
        );
    }

    /**
     * The form the CP renders for a new endpoint must offer the same default,
     * or the checkbox arrives unticked and the value above is a fluke of the
     * request body this test happens to send.
     */
    public function test_the_create_form_offers_replay_protection_ticked(): void
    {
        $vorlage = (new \ReflectionMethod(
            InboundController::class, 'create'
        ))->getFileName();

        $quelle = file_get_contents((string) $vorlage);

        $this->assertStringContainsString("'replay_protection_enabled' => true,", $quelle);
        $this->assertStringNotContainsString("'replay_protection_enabled' => false,", $quelle);
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

    public function test_the_brand_resolver_leads_the_stack(): void
    {
        $stack = WebhookManagerServiceProvider::inboundMiddleware();

        $this->assertSame(ResolveInboundBrand::class, $stack[0] ?? null);
    }

    /**
     * A brand handle the router cannot match must not be printed as if it
     * could. `Brand` is unguarded and its handle column carries only a unique
     * index, so nothing stops `Chor.de` from existing.
     */
    public function test_a_brand_handle_the_router_cannot_match_is_not_printed_into_the_url(): void
    {
        $this->assertSame(
            '/webhooks/inbound/chorgesucht/events',
            WebhookManagerServiceProvider::inboundPath('events', 'chorgesucht'),
        );

        $this->assertSame(
            '/webhooks/inbound/events',
            WebhookManagerServiceProvider::inboundPath('events', 'Chor.de'),
        );
    }
}
