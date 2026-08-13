<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Goldnead\WebhookManager\Domain\Log\Models\LogEntry;
use Goldnead\WebhookManager\Http\Middleware\ResolveInboundBrand;
use Goldnead\WebhookManager\Tests\TestCase;
use Goldnead\WebhookManager\WebhookManagerServiceProvider;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

/**
 * The inbound endpoint is a machine-to-machine URL. It must run its own
 * middleware stack, not the application's `web` group.
 *
 * History this test locks down: the endpoint used to be registered through
 * Statamic's `$routes['web']` hook, which drops it inside the `web` group.
 * `web` contains ValidateCsrfToken, so an external sender — which has no
 * session and no CSRF token — was answered 419 before its credentials were
 * ever looked at. That was patched with a per-route
 * `withoutMiddleware([ValidateCsrfToken::class])`, which removes exactly one
 * member of the group and only while the host app uses that exact class name.
 *
 * Sitting outside `web` also means the endpoint must carry its own weight:
 * the three request-shaped tests below assert that authentication is what
 * actually rejects callers, and that it is checked before any side effect.
 *
 * The last test is the guard rail: taking one route out of CSRF protection
 * must not take the CP out with it.
 */
class InboundRouteMiddlewareStackTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_HEADER = 'X-Test-Webhook-Key';

    /**
     * Routes registered after the container booted are not in the router's
     * name lookup table yet. A real request refreshes it while matching
     * (RouteCollection::toSymfonyRouteCollection); testbench never gets that
     * far when a test only inspects the collection.
     */
    private function namedRoute(string $name)
    {
        Route::getRoutes()->refreshNameLookups();

        return Route::getRoutes()->getByName($name);
    }

    /**
     * Generated per run — never a real credential, and nothing here is a
     * secret worth committing.
     */
    private function testToken(): string
    {
        return 'test-'.bin2hex(random_bytes(16));
    }

    private function makeEndpoint(string $token): InboundEndpoint
    {
        return InboundEndpoint::create([
            'name' => 'ESP feedback',
            'handle' => 'esp-events',
            'enabled' => true,
            'path' => 'esp-events',
            'allowed_methods' => ['POST'],
            'auth_type' => 'static_header',
            'auth_config' => ['header' => self::TEST_HEADER, 'value' => $token],
            'expected_content_type' => 'application/json',
            'max_payload_kb' => 512,
            'replay_protection_enabled' => false,
            'logging_mode' => 'partial',
            'action_type' => 'audit_log',
            'action_config' => [],
        ]);
    }

    // ---------------------------------------------------------------- 1 of 3

    public function test_request_without_auth_header_is_rejected_by_auth_not_by_csrf(): void
    {
        $this->makeEndpoint($this->testToken());

        $response = $this->postJson('/webhooks/inbound/esp-events', ['event' => 'bounce']);

        // 419 would mean CSRF fired first and auth never ran at all.
        $response->assertStatus(401)->assertJson(['ok' => false]);

        // Fail closed: nothing was dispatched.
        $this->assertSame(0, LogEntry::where('type', 'inbound_audit')->count());
    }

    // ---------------------------------------------------------------- 2 of 3

    public function test_request_with_wrong_token_is_rejected_the_same_way(): void
    {
        $this->makeEndpoint($this->testToken());

        $response = $this->postJson(
            '/webhooks/inbound/esp-events',
            ['event' => 'bounce'],
            [self::TEST_HEADER => $this->testToken()], // a different token
        );

        $response->assertStatus(401)->assertJson(['ok' => false]);
        $this->assertSame(0, LogEntry::where('type', 'inbound_audit')->count());
    }

    public function test_an_empty_token_does_not_authenticate(): void
    {
        $this->makeEndpoint($this->testToken());

        $this->postJson(
            '/webhooks/inbound/esp-events',
            ['event' => 'bounce'],
            [self::TEST_HEADER => ''],
        )->assertStatus(401);
    }

    // ---------------------------------------------------------------- 3 of 3

    public function test_request_with_correct_token_is_accepted_and_processed(): void
    {
        $token = $this->testToken();
        $this->makeEndpoint($token);

        $response = $this->postJson(
            '/webhooks/inbound/esp-events',
            ['event' => 'bounce', 'email' => 'bounced@example.com'],
            [self::TEST_HEADER => $token],
        );

        $response->assertSuccessful()->assertJson(['ok' => true]);

        // The status code alone proves nothing — the message has to have been
        // processed. The audit_log action writes this row.
        $this->assertSame(1, LogEntry::where('type', 'inbound_audit')->count());
    }

    // ------------------------------------------------------------ guard rail

    /**
     * The CP is a browser surface and stays behind CSRF. This is the test
     * that matters most: it stops the next person from widening the exemption
     * from "the one machine endpoint" to "the addon".
     *
     * It cannot be a request-shaped test. ValidateCsrfToken short-circuits
     * while `runningUnitTests()`, so no HTTP assertion can ever observe a 419;
     * and Statamic only registers CP routes during a full site boot, which
     * orchestra/testbench does not perform, so there is no CP route object to
     * inspect here either. (The live CP stack is verified against staging with
     * `route:list` — see the PR.)
     *
     * What is checkable here is the thing that would actually have to change
     * for the CP to lose CSRF, and all three are asserted:
     */
    public function test_csrf_still_applies_to_the_addon_cp_routes(): void
    {
        $provider = $this->app->getProvider(WebhookManagerServiceProvider::class);
        $routes = (new \ReflectionProperty($provider, 'routes'))->getValue($provider);

        // 1. CP routes still go through Statamic's route hook, which registers
        //    them inside the CSRF-protected CP middleware group. Only the
        //    inbound endpoint was taken out of Statamic's hook.
        $this->assertSame(['cp'], array_keys($routes),
            'only CP routes may be registered through Statamic; inbound is registered explicitly');
        $this->assertStringEndsWith('routes/cp.php', $routes['cp']);

        // 2. No CP route opts out of CSRF on its own.
        $cpSource = file_get_contents($routes['cp']);
        $this->assertStringNotContainsString('withoutMiddleware', $cpSource,
            'no CP route may opt out of middleware');
        $this->assertStringNotContainsString('ValidateCsrfToken', $cpSource);
        $this->assertStringNotContainsString('VerifyCsrfToken', $cpSource);

        // 3. Nothing but the inbound endpoint runs the CSRF-free stack. If
        //    someone ever moves a CP route into this group, this fails.
        Route::getRoutes()->refreshNameLookups();

        $exempt = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => array_values($r->gatherMiddleware()) === WebhookManagerServiceProvider::inboundMiddleware());

        $this->assertGreaterThan(0, $exempt->count(), 'the inbound routes exist');

        foreach ($exempt as $route) {
            $this->assertStringStartsWith(
                'webhook-manager.inbound.',
                (string) $route->getName(),
                "route [{$route->uri()}] must not run the inbound CSRF-free stack",
            );
        }
    }

    public function test_inbound_route_does_not_inherit_the_web_stack(): void
    {
        $route = $this->namedRoute('webhook-manager.inbound.handle');
        $this->assertNotNull($route, 'inbound route is registered');

        $middleware = $route->gatherMiddleware();

        $this->assertNotContains('web', $middleware);
        $this->assertNotContains(ValidateCsrfToken::class, $middleware);
        $this->assertNotContains(StartSession::class, $middleware);
        $this->assertNotContains(EncryptCookies::class, $middleware);

        // And it is the declared stack, not an accident.
        $this->assertSame(
            WebhookManagerServiceProvider::inboundMiddleware(),
            array_values($middleware),
        );
    }

    /**
     * ResolveInboundBrand is in the stack, first, and cannot be configured out.
     *
     * Without it the brand-scoped endpoint lookup runs with no current brand,
     * fails closed, and every delivery on a multi-brand install is answered
     * 404. An install that published `config/webhook-manager.php` before this
     * middleware existed has the old list frozen in its own file and would
     * otherwise upgrade straight back into that outage; `inboundMiddleware()`
     * prepends regardless, which is what this asserts.
     *
     * That it runs *before* whatever else the stack contains is a claim about
     * runtime, not about an array, and is proved where it can be:
     * `InboundEndpointDefaultsAndRouteOrderTest` registers a hostile
     * `Route::bind('brand')` and sends a real delivery through.
     */
    public function test_the_brand_resolver_leads_the_stack_and_survives_a_stale_published_config(): void
    {
        // What a config file published before 2.1.0 still contains.
        config()->set('webhook-manager.inbound.middleware', [SubstituteBindings::class]);

        $stack = WebhookManagerServiceProvider::inboundMiddleware();

        $this->assertSame(ResolveInboundBrand::class, $stack[0] ?? null);
        $this->assertContains(SubstituteBindings::class, $stack);

        // Even an operator who empties the list entirely keeps it.
        config()->set('webhook-manager.inbound.middleware', []);
        $this->assertSame([ResolveInboundBrand::class], WebhookManagerServiceProvider::inboundMiddleware());
    }

    /** The shipped stack carries the brand resolver and nothing else. */
    public function test_the_shipped_stack_is_the_brand_resolver_alone(): void
    {
        $this->assertSame([], WebhookManagerServiceProvider::DEFAULT_INBOUND_MIDDLEWARE);
        $this->assertSame([ResolveInboundBrand::class], WebhookManagerServiceProvider::inboundMiddleware());
    }

    /**
     * The endpoint has to answer on the URL the CP shows. Before v1.8.0 the
     * shipped prefix was Statamic's `!/` utility prefix while every
     * human-facing default in the addon spelled it without one, and a POST to
     * the URL people actually used fell through to Statamic's front-end
     * catch-all — which is in `web`, and therefore answered 419.
     */
    public function test_the_cp_prefix_and_the_routed_prefix_are_the_same_string(): void
    {
        $route = $this->namedRoute('webhook-manager.inbound.handle');
        $this->assertNotNull($route, 'inbound route is registered');

        $this->assertSame(
            trim(WebhookManagerServiceProvider::inboundRoutePrefix(), '/').'/{handle}',
            $route->uri(),
        );
    }

    /**
     * Anyone who configured a sender against the pre-v1.8.0 URL keeps working.
     */
    public function test_the_legacy_prefix_is_still_routable(): void
    {
        $token = $this->testToken();
        $this->makeEndpoint($token);

        $this->postJson(
            '/!/webhooks/inbound/esp-events',
            ['event' => 'bounce'],
            [self::TEST_HEADER => $token],
        )->assertSuccessful()->assertJson(['ok' => true]);

        // Same auth, same rejection.
        $this->postJson('/!/webhooks/inbound/esp-events', ['event' => 'bounce'])
            ->assertStatus(401);
    }
}
