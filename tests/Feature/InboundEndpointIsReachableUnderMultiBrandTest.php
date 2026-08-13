<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\BrandContext\Facades\BrandContext;
use Goldnead\WebhookManager\Auth\Support\SignatureGenerator;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Actions\CreateInboundEndpointAction;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Goldnead\WebhookManager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * The inbound endpoint, exercised over HTTP with multi-brand switched on.
 *
 * This is the case the suite did not have. Every inbound test ran single-brand,
 * where `BrandScope` is a no-op, and every brand-isolation test asserted at the
 * model layer without ever making a request. In the gap between them sat a
 * complete outage: on a multi-brand install the brand-scoped lookup in
 * `InboundWebhookController` ran with no current brand — a webhook sender has
 * no session, no bearer token and no link token — the scope failed closed, and
 * every delivery was answered `404 Endpoint not found or disabled` while the
 * endpoint sat enabled in the table.
 *
 * The first test here is that outage, written as the behaviour that must now
 * hold. The rest fence in what the repair may not cost: isolation between
 * brands, the signature check, and the duplicate guard.
 */
class InboundEndpointIsReachableUnderMultiBrandTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'inbound-shared-secret';

    protected function setUp(): void
    {
        parent::setUp();

        // Same reason as BrandIsolationTest: flipped after boot so the CP
        // wiring stays out of it. BrandScope reads the flag at query time.
        config()->set('brand-context.multi_brand', true);
        config()->set('brand-context.license_check', null);

        app('brand-context')->forget();
    }

    private function makeBrand(string $handle, string $name): int
    {
        return (int) DB::table('brands')->insertGetId([
            'handle' => $handle,
            'name' => $name,
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeEndpoint(int $brandId, string $handle, string $secret = self::SECRET): InboundEndpoint
    {
        return BrandContext::runFor($brandId, fn () => InboundEndpoint::create([
            'name' => 'Inbound '.$handle,
            'handle' => $handle,
            'enabled' => true,
            'path' => $handle,
            'allowed_methods' => ['POST'],
            'auth_type' => 'hmac',
            'auth_config' => ['secret' => $secret, 'algorithm' => 'sha256'],
            'expected_content_type' => 'application/json',
            'replay_protection_enabled' => true,
            'action_type' => 'noop',
        ]));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function deliver(string $url, array $payload = ['hello' => 'world'], ?string $secret = self::SECRET): TestResponse
    {
        $body = json_encode($payload);

        $headers = ['CONTENT_TYPE' => 'application/json'];

        if ($secret !== null) {
            $headers['HTTP_X_WEBHOOK_SIGNATURE'] = SignatureGenerator::compute($body, $secret, 'sha256');
        }

        // A real delivery arrives with no session and no brand of any kind.
        // Nothing in this method may set one, or the test proves nothing.
        app('brand-context')->forget();

        return $this->call('POST', $url, [], [], [], $headers, $body);
    }

    public function test_a_delivery_reaches_an_endpoint_of_a_non_default_brand(): void
    {
        $brand = $this->makeBrand('brand-a', 'Brand A');
        $this->makeEndpoint($brand, 'scaleway-events');

        $this->deliver('/webhooks/inbound/brand-a/scaleway-events')
            ->assertStatus(200);
    }

    public function test_the_same_handle_in_two_brands_resolves_to_the_addressed_one(): void
    {
        $a = $this->makeBrand('brand-a', 'Brand A');
        $b = $this->makeBrand('brand-b', 'Brand B');

        // `handle` is unique per brand, not globally — this pair is legal, and
        // it is the reason the brand may never be inferred from the handle.
        $endpointA = $this->makeEndpoint($a, 'events', 'secret-a');
        $endpointB = $this->makeEndpoint($b, 'events', 'secret-b');

        $this->assertNotSame($endpointA->id, $endpointB->id);

        // Each URL accepts only its own brand's secret …
        $this->deliver('/webhooks/inbound/brand-a/events', ['n' => 1], 'secret-a')->assertStatus(200);
        $this->deliver('/webhooks/inbound/brand-b/events', ['n' => 2], 'secret-b')->assertStatus(200);

        // … and rejects the other's, which is what "isolated" has to mean here:
        // a webhook config is a destination plus the credential that signs it.
        $this->deliver('/webhooks/inbound/brand-a/events', ['n' => 3], 'secret-b')->assertStatus(401);
    }

    public function test_addressing_an_endpoint_under_a_brand_that_does_not_own_it_is_404(): void
    {
        $a = $this->makeBrand('brand-a', 'Brand A');
        $this->makeBrand('brand-b', 'Brand B');
        $this->makeEndpoint($a, 'only-in-a');

        $this->deliver('/webhooks/inbound/brand-b/only-in-a')
            ->assertStatus(404)
            ->assertJson(['ok' => false]);
    }

    public function test_an_unknown_brand_segment_is_404_and_not_a_different_error(): void
    {
        $a = $this->makeBrand('brand-a', 'Brand A');
        $this->makeEndpoint($a, 'events');

        // Same answer as an unknown endpoint handle, so the URL cannot be used
        // to find out which brands exist.
        $this->deliver('/webhooks/inbound/brand-does-not-exist/events')
            ->assertStatus(404)
            ->assertJson(['ok' => false]);
    }

    public function test_the_brandless_url_resolves_the_default_brand(): void
    {
        $default = (int) DB::table('brands')->where('is_default', true)->value('id');
        $this->assertNotEmpty($default, 'expected brand-context to have seeded a default brand');

        $this->makeEndpoint($default, 'legacy-sender');

        // An install that flips multi_brand on keeps the endpoints that worked
        // before it flipped — the brand-scoping migration backfilled them all
        // onto the default brand.
        $this->deliver('/webhooks/inbound/legacy-sender')->assertStatus(200);
    }

    public function test_the_brandless_url_does_not_search_other_brands(): void
    {
        $a = $this->makeBrand('brand-a', 'Brand A');
        $this->makeEndpoint($a, 'only-in-a');

        // Resolving this by scanning every brand would work right up to the day
        // a second brand picks the same handle, and would then have to guess.
        $this->deliver('/webhooks/inbound/only-in-a')
            ->assertStatus(404)
            ->assertJson(['ok' => false]);
    }

    public function test_a_wrong_signature_is_rejected_before_anything_happens(): void
    {
        $brand = $this->makeBrand('brand-a', 'Brand A');
        $this->makeEndpoint($brand, 'guarded');

        $body = json_encode(['hello' => 'world']);

        app('brand-context')->forget();

        $this->call('POST', '/webhooks/inbound/brand-a/guarded', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => 'not-the-signature',
        ], $body)->assertStatus(401);

        // Unsigned is not "unauthenticated but harmless" either.
        $this->deliver('/webhooks/inbound/brand-a/guarded', ['hello' => 'world'], null)
            ->assertStatus(401);
    }

    public function test_the_same_delivery_sent_twice_is_rejected_the_second_time(): void
    {
        $brand = $this->makeBrand('brand-a', 'Brand A');
        $this->makeEndpoint($brand, 'once-only');

        $payload = ['order' => 'A-1'];

        $this->deliver('/webhooks/inbound/brand-a/once-only', $payload)->assertStatus(200);
        $this->deliver('/webhooks/inbound/brand-a/once-only', $payload)
            ->assertStatus(409)
            ->assertJson(['ok' => false]);
    }

    public function test_the_replay_guard_is_per_endpoint_not_global(): void
    {
        $a = $this->makeBrand('brand-a', 'Brand A');
        $b = $this->makeBrand('brand-b', 'Brand B');
        $this->makeEndpoint($a, 'events', 'secret-a');
        $this->makeEndpoint($b, 'events', 'secret-b');

        // Identical body, two brands. The second must not be swallowed as a
        // duplicate of the first — they are different integrations.
        $this->deliver('/webhooks/inbound/brand-a/events', ['n' => 1], 'secret-a')->assertStatus(200);
        $this->deliver('/webhooks/inbound/brand-b/events', ['n' => 1], 'secret-b')->assertStatus(200);
    }

    public function test_a_delivery_does_not_leave_a_brand_behind_in_the_manager(): void
    {
        $brand = $this->makeBrand('brand-a', 'Brand A');
        $this->makeEndpoint($brand, 'events');

        $this->deliver('/webhooks/inbound/brand-a/events')->assertStatus(200);

        // The manager is a singleton. In a long-lived process the next thing to
        // run must not inherit this delivery's brand.
        $this->assertFalse(app('brand-context')->hasCurrent());
    }

    public function test_new_endpoints_default_to_replay_protection_on(): void
    {
        $brand = $this->makeBrand('brand-a', 'Brand A');

        $endpoint = BrandContext::runFor($brand, fn () => app(
            CreateInboundEndpointAction::class
        )([
            'name' => 'Fresh endpoint',
            'handle' => 'fresh',
            'auth_type' => 'hmac',
            'auth_config' => ['secret' => self::SECRET],
        ]));

        $this->assertTrue((bool) $endpoint->replay_protection_enabled);
    }
}
