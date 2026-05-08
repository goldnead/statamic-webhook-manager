<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Goldnead\WebhookManager\Domain\Log\Models\LogEntry;
use Goldnead\WebhookManager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * End-to-end pipeline test using the `audit_log` action so the test
 * doesn't depend on a Statamic collection / Entry::make() being
 * resolvable inside Orchestra Testbench.
 *
 * The same pipeline drives `create_entry`, `update_entry` etc. — only
 * the final handler differs.
 */
class InboundEndpointDispatchesActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbound_request_runs_pipeline_and_writes_audit_log(): void
    {
        $endpoint = InboundEndpoint::create([
            'name' => 'Lead intake',
            'handle' => 'lead-intake',
            'enabled' => true,
            'path' => 'lead-intake',
            'allowed_methods' => ['POST'],
            'auth_type' => 'static_header',
            'auth_config' => [
                'header' => 'X-API-Key',
                'value' => 'super-secret-key',
            ],
            'expected_content_type' => 'application/json',
            'max_payload_kb' => 64,
            'replay_protection_enabled' => false,
            'logging_mode' => 'partial',
            'mapping_config' => [
                'email' => ['path' => 'contact.email', 'required' => true],
                'name' => ['path' => 'contact.name'],
            ],
            'action_type' => 'audit_log',
            'action_config' => [],
            'response_config' => null,
        ]);

        $response = $this->postJson(
            "/!/webhooks/inbound/{$endpoint->handle}",
            ['contact' => ['email' => 'a@example.com', 'name' => 'Anna']],
            ['X-API-Key' => 'super-secret-key'],
        );

        $response->assertStatus(200)
            ->assertJson(['ok' => true]);

        // Audit log handler wrote a LogEntry of type `inbound_audit`.
        $this->assertSame(1, LogEntry::where('type', 'inbound_audit')->count());

        // Plus the controller's `inbound_received` info log.
        $this->assertSame(1, LogEntry::where('type', 'inbound_received')->count());
    }

    public function test_inbound_request_rejects_unknown_handle_with_404(): void
    {
        $response = $this->postJson('/!/webhooks/inbound/does-not-exist', []);

        $response->assertStatus(404)
            ->assertJson(['ok' => false]);
    }

    public function test_inbound_request_rejects_disabled_endpoint_with_404(): void
    {
        InboundEndpoint::create([
            'name' => 'Disabled',
            'handle' => 'disabled',
            'enabled' => false,
            'path' => 'disabled',
            'allowed_methods' => ['POST'],
            'auth_type' => 'none',
            'expected_content_type' => 'application/json',
            'action_type' => 'noop',
        ]);

        $response = $this->postJson('/!/webhooks/inbound/disabled', []);

        $response->assertStatus(404);
    }

    public function test_inbound_request_returns_405_for_disallowed_methods(): void
    {
        InboundEndpoint::create([
            'name' => 'POST only',
            'handle' => 'post-only',
            'enabled' => true,
            'path' => 'post-only',
            'allowed_methods' => ['POST'],
            'auth_type' => 'none',
            'expected_content_type' => 'application/json',
            'action_type' => 'noop',
        ]);

        $response = $this->getJson('/!/webhooks/inbound/post-only');

        $response->assertStatus(405);
    }

    public function test_inbound_request_returns_422_on_mapping_failure(): void
    {
        InboundEndpoint::create([
            'name' => 'Strict mapping',
            'handle' => 'strict-mapping',
            'enabled' => true,
            'path' => 'strict-mapping',
            'allowed_methods' => ['POST'],
            'auth_type' => 'none',
            'expected_content_type' => 'application/json',
            'mapping_config' => [
                'email' => ['path' => 'email', 'required' => true],
            ],
            'action_type' => 'noop',
        ]);

        $response = $this->postJson('/!/webhooks/inbound/strict-mapping', ['name' => 'No email here']);

        $response->assertStatus(422)
            ->assertJson(['ok' => false]);
    }
}
