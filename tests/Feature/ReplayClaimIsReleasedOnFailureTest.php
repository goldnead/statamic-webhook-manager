<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Contracts\InboundActionHandlerInterface;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Goldnead\WebhookManager\Registries\InboundActionHandlerRegistry;
use Goldnead\WebhookManager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Der Replay-Schlüssel wird vor Mapping und Aktion geclaimt. Scheitert beides,
 * darf er nicht stehen bleiben: Der Retry des Senders würde sonst mit 409
 * abgewiesen und das Ereignis ginge dauerhaft verloren.
 */
class ReplayClaimIsReleasedOnFailureTest extends TestCase
{
    use RefreshDatabase;

    private function endpoint(array $overrides = []): InboundEndpoint
    {
        return InboundEndpoint::create(array_merge([
            'name' => 'Replay',
            'handle' => 'replay-ep',
            'enabled' => true,
            'path' => 'replay-ep',
            'allowed_methods' => ['POST'],
            'auth_type' => 'none',
            'expected_content_type' => 'application/json',
            'replay_protection_enabled' => true,
            'action_type' => 'noop',
            'action_config' => [],
        ], $overrides));
    }

    public function test_retry_after_mapping_failure_is_not_blocked_as_duplicate(): void
    {
        $this->endpoint(['mapping_config' => [
            'email' => ['path' => 'email', 'required' => true],
        ]]);
        $headers = ['Idempotency-Key' => 'evt_1'];

        $this->postJson('/webhooks/inbound/replay-ep', ['x' => 1], $headers)->assertStatus(422);

        // Derselbe Schlüssel, jetzt mit gültigem Payload: darf nicht 409 sein.
        $this->postJson('/webhooks/inbound/replay-ep', ['email' => 'a@example.com'], $headers)
            ->assertStatus(200);
    }

    public function test_retry_after_failing_action_is_not_blocked_as_duplicate(): void
    {
        $this->endpoint(['action_type' => 'flaky']);
        $flaky = new class implements InboundActionHandlerInterface
        {
            public bool $fail = true;

            public function handle(): string
            {
                return 'flaky';
            }

            public function label(): string
            {
                return 'Flaky';
            }

            public function handleAction(InboundEndpoint $endpoint, array $mappedPayload, array $rawPayload): array
            {
                if ($this->fail) {
                    throw new \RuntimeException('boom');
                }

                return ['ok' => true, 'message' => 'done', 'data' => []];
            }
        };
        app(InboundActionHandlerRegistry::class)->register($flaky);
        $headers = ['Idempotency-Key' => 'evt_2'];

        $this->postJson('/webhooks/inbound/replay-ep', ['a' => 1], $headers)->assertStatus(422);

        $flaky->fail = false;
        $this->postJson('/webhooks/inbound/replay-ep', ['a' => 1], $headers)->assertStatus(200);
    }

    public function test_successful_request_is_still_blocked_as_duplicate(): void
    {
        $this->endpoint();
        $headers = ['Idempotency-Key' => 'evt_3'];

        $this->postJson('/webhooks/inbound/replay-ep', ['a' => 1], $headers)->assertStatus(200);
        $this->postJson('/webhooks/inbound/replay-ep', ['a' => 1], $headers)->assertStatus(409);
    }
}
