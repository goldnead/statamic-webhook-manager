<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Domain\Delivery\Actions\CreateDeliverySnapshotAction;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Events\DeliveryFailedTerminally;
use Goldnead\WebhookManager\Notifications\DeliveryFailedNotification;
use Goldnead\WebhookManager\Services\CircuitBreaker;
use Goldnead\WebhookManager\Services\DeliveryEngine;
use Goldnead\WebhookManager\Tests\TestCase;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

class FailureAlertingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The alert throttle lives in the cache, which (unlike the DB) is not
        // reset between tests; flush it so per-hook throttling stays isolated.
        \Illuminate\Support\Facades\Cache::flush();
    }

    protected function failingHook(array $overrides = []): OutboundWebhook
    {
        return OutboundWebhook::create(array_merge([
            'name' => 'Broken', 'handle' => 'broken-'.uniqid(), 'enabled' => true,
            'trigger_type' => 'entry.published', 'url' => 'https://example.com/down',
            'method' => 'POST', 'auth_type' => 'none', 'payload_type' => 'raw_json',
            'payload_template' => '{}', 'queue_enabled' => false,
            'retry_strategy' => ['strategy' => 'none'], // first failure is terminal
        ], $overrides));
    }

    protected function deliver(OutboundWebhook $hook): Delivery
    {
        $event = new TriggerEvent('entry.published', 'entry', '1', ['id' => '1', 'title' => 'X'], 'default');
        $delivery = app(CreateDeliverySnapshotAction::class)($hook, new ExecutionContext($event));

        return app(DeliveryEngine::class)->send($delivery);
    }

    public function test_terminal_failure_dispatches_event_and_increments_breaker(): void
    {
        Notification::fake();
        Http::fake(['https://example.com/*' => Http::response('nope', 500)]);

        $hook = $this->failingHook();
        $delivery = $this->deliver($hook);

        $this->assertSame(Delivery::STATUS_FAILED, $delivery->status);
        $this->assertSame(1, (int) $hook->fresh()->consecutive_failures);
    }

    public function test_breaker_disables_the_hook_at_threshold(): void
    {
        config()->set('webhook-manager.circuit_breaker.threshold', 2);
        Notification::fake();
        Http::fake(['https://example.com/*' => Http::response('nope', 500)]);

        $hook = $this->failingHook();

        $this->deliver($hook);
        $this->assertTrue((bool) $hook->fresh()->enabled, 'still enabled after 1 failure');

        $this->deliver($hook->fresh());
        $hook = $hook->fresh();
        $this->assertFalse((bool) $hook->enabled, 'auto-disabled after threshold');
        $this->assertSame(2, (int) $hook->consecutive_failures);
    }

    public function test_success_resets_the_breaker(): void
    {
        $hook = $this->failingHook(['consecutive_failures' => 5]);
        Http::fake(['https://example.com/*' => Http::response(['ok' => true], 200)]);

        $this->deliver($hook);

        $this->assertSame(0, (int) $hook->fresh()->consecutive_failures);
    }

    public function test_listener_alerts_via_mail_and_slack_then_throttles(): void
    {
        Notification::fake();
        config()->set('webhook-manager.alerts.mail.recipients', ['ops@example.com']);
        config()->set('webhook-manager.alerts.slack.webhook_url', 'https://hooks.slack.com/alert');
        Http::fake(['https://hooks.slack.com/*' => Http::response('ok', 200)]);

        $hook = $this->failingHook();
        $delivery = Delivery::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'outbound_webhook_id' => $hook->id,
            'trigger_type' => 'entry.published',
            'status' => Delivery::STATUS_FAILED,
            'request_method' => 'POST',
            'request_url' => $hook->url,
            'response_status' => 500,
            'attempts' => 3,
            'error_type' => 'server',
            'error_message' => 'HTTP 500',
        ]);

        $listener = app(\Goldnead\WebhookManager\Listeners\SendFailureAlertListener::class);

        // First terminal failure → alert fires (mail + slack), throttle armed.
        $listener->handle(new DeliveryFailedTerminally($delivery));
        Notification::assertSentOnDemand(DeliveryFailedNotification::class);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'hooks.slack.com'));

        // Second within the throttle window → suppressed.
        Notification::fake();
        $listener->handle(new DeliveryFailedTerminally($delivery));
        Notification::assertNothingSent();
    }
}
