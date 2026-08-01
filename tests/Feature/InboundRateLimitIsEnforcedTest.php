<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Goldnead\WebhookManager\Domain\Log\Models\LogEntry;
use Goldnead\WebhookManager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * `inbound.rate_limit_per_minute` was declared in config, rendered into the
 * Settings screen as a live "Rate limit (per minute)" security field, and sold
 * in MARKETPLACE.md as the headline feature of the Pro tier — while nothing in
 * src/ ever read the key. A security control an operator believes is protecting
 * a public endpoint, and is not, is worse than no control at all.
 *
 * These tests are what makes the field true.
 */
class InboundRateLimitIsEnforcedTest extends TestCase
{
    use RefreshDatabase;

    protected function makeEndpoint(string $handle, array $overrides = []): InboundEndpoint
    {
        return InboundEndpoint::create(array_merge([
            'name' => 'Intake '.$handle,
            'handle' => $handle,
            'enabled' => true,
            'path' => $handle,
            'allowed_methods' => ['POST'],
            'auth_type' => 'none',
            'auth_config' => [],
            'expected_content_type' => 'application/json',
            'max_payload_kb' => 64,
            'replay_protection_enabled' => false,
            'logging_mode' => 'partial',
            'mapping_config' => [],
            'action_type' => 'audit_log',
            'action_config' => [],
            'response_config' => null,
        ], $overrides));
    }

    protected function deliver(string $handle)
    {
        return $this->postJson("/webhooks/inbound/{$handle}", ['ping' => true]);
    }

    public function test_requests_beyond_the_configured_limit_are_rejected_with_429(): void
    {
        config()->set('webhook-manager.inbound.rate_limit_per_minute', 3);

        $this->makeEndpoint('throttled');

        for ($i = 1; $i <= 3; $i++) {
            $this->deliver('throttled')->assertStatus(200);
        }

        $blocked = $this->deliver('throttled');

        $blocked->assertStatus(429)
            ->assertJson(['ok' => false])
            ->assertHeader('X-RateLimit-Limit', '3')
            ->assertHeader('X-RateLimit-Remaining', '0');

        $this->assertNotNull($blocked->headers->get('Retry-After'));
    }

    public function test_a_successful_delivery_reports_the_remaining_quota(): void
    {
        config()->set('webhook-manager.inbound.rate_limit_per_minute', 5);

        $this->makeEndpoint('counted');

        $this->deliver('counted')
            ->assertStatus(200)
            ->assertHeader('X-RateLimit-Limit', '5')
            ->assertHeader('X-RateLimit-Remaining', '4');
    }

    public function test_the_limit_is_per_endpoint_not_global(): void
    {
        config()->set('webhook-manager.inbound.rate_limit_per_minute', 1);

        $this->makeEndpoint('alpha');
        $this->makeEndpoint('beta');

        $this->deliver('alpha')->assertStatus(200);
        $this->deliver('alpha')->assertStatus(429);

        // A noisy sender on one endpoint must not take the others down.
        $this->deliver('beta')->assertStatus(200);
    }

    public function test_an_endpoint_can_override_the_global_limit(): void
    {
        config()->set('webhook-manager.inbound.rate_limit_per_minute', 100);

        $this->makeEndpoint('strict', ['rate_limit_config' => ['per_minute' => 1]]);

        $this->deliver('strict')->assertStatus(200);
        $this->deliver('strict')->assertStatus(429);
    }

    public function test_a_zero_limit_disables_throttling(): void
    {
        config()->set('webhook-manager.inbound.rate_limit_per_minute', 0);

        $this->makeEndpoint('unlimited');

        for ($i = 1; $i <= 10; $i++) {
            $this->deliver('unlimited')->assertStatus(200);
        }

        $this->assertNull($this->deliver('unlimited')->headers->get('X-RateLimit-Limit'));
    }

    public function test_the_legacy_prefix_is_throttled_by_the_same_counter(): void
    {
        // The pre-1.8.0 URL stays routable. It must not be a way around the
        // limit — same endpoint, same bucket.
        config()->set('webhook-manager.inbound.rate_limit_per_minute', 1);

        $this->makeEndpoint('legacy-shared');

        $this->deliver('legacy-shared')->assertStatus(200);

        $this->postJson('/!/webhooks/inbound/legacy-shared', ['ping' => true])
            ->assertStatus(429);
    }

    public function test_throttling_is_logged_so_it_is_visible_in_the_cp(): void
    {
        config()->set('webhook-manager.inbound.rate_limit_per_minute', 1);

        $this->makeEndpoint('logged');

        $this->deliver('logged')->assertStatus(200);
        $this->deliver('logged')->assertStatus(429);

        $this->assertSame(
            1,
            LogEntry::where('type', 'inbound_rate_limited')->count(),
            'A throttled delivery left no trace in the log, so nobody can tell a limit from an outage.'
        );
    }
}
