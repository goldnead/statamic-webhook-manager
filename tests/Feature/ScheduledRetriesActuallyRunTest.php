<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Carbon\Carbon;
use Goldnead\WebhookManager\Domain\Delivery\Actions\CreateDeliverySnapshotAction;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Services\DeliveryEngine;
use Goldnead\WebhookManager\Tests\TestCase;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

/**
 * Retries were planned and never executed.
 *
 * `RetryPlanner` computed the next attempt, `DeliveryEngine` wrote it to
 * `next_retry_at`, the CP rendered it as "next retry in 30 seconds", and
 * `DeliveryRepository::readyForRetry()` — the query written to find those rows —
 * had **no caller anywhere in the package**. `ProcessOutboundDeliveryJob` sets
 * `$tries = 1` and says in its own docblock that "a scheduled job dispatcher
 * (or the replay command) picks it up". No such dispatcher existed.
 *
 * The consequence is worse than a missing feature: the operator sees a delivery
 * row that is waiting for an attempt that never comes, on a product whose
 * one-line description says "deliveries, retries". The payload is lost and
 * nothing says so.
 */
class ScheduledRetriesActuallyRunTest extends TestCase
{
    use RefreshDatabase;

    protected function makeFailingDelivery(array $hookOverrides = []): Delivery
    {
        $hook = OutboundWebhook::create(array_merge([
            'name' => 'Flaky endpoint',
            'handle' => 'flaky',
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'url' => 'https://example.com/flaky',
            'method' => 'POST',
            'auth_type' => 'none',
            'payload_type' => 'raw_json',
            'payload_template' => '{"id":"{{ entry:id }}"}',
            'queue_enabled' => false,
        ], $hookOverrides));

        $context = new ExecutionContext(new TriggerEvent(
            triggerHandle: 'entry.published',
            sourceType: 'entry',
            sourceReference: '42',
            payload: ['id' => '42'],
            site: 'default',
        ));

        return ($this->app->make(CreateDeliverySnapshotAction::class))($hook, $context);
    }

    public function test_a_transient_failure_schedules_a_retry_that_the_command_then_runs(): void
    {
        // Fails once, then succeeds — the ordinary transient outage the retry
        // policy exists for.
        Http::fake(['https://example.com/flaky' => Http::sequence()
            ->push('boom', 503)
            ->push(['ok' => true], 200),
        ]);

        $delivery = $this->makeFailingDelivery();

        $delivery = $this->app->make(DeliveryEngine::class)->send($delivery);

        // Precondition: the planner did its half of the job.
        $this->assertSame(Delivery::STATUS_FAILED, $delivery->status);
        $this->assertNotNull($delivery->next_retry_at, 'No retry was planned, so this test proves nothing.');
        $this->assertSame(1, $delivery->attempts);

        // Nothing is due yet, so nothing may run.
        $this->artisan('webhook-manager:dispatch-retries')->assertSuccessful();
        $this->assertSame(1, $delivery->fresh()->attempts);

        // Once the scheduled time passes, the attempt must actually happen.
        Carbon::setTestNow($delivery->next_retry_at->copy()->addSecond());

        $this->artisan('webhook-manager:dispatch-retries')->assertSuccessful();

        $delivery = $delivery->fresh();

        $this->assertSame(2, $delivery->attempts, 'The due retry never ran — the delivery is silently lost.');
        $this->assertSame(Delivery::STATUS_SUCCESS, $delivery->status);
        $this->assertNull($delivery->next_retry_at);

        Carbon::setTestNow();
    }

    public function test_a_delivery_is_not_dispatched_twice_for_the_same_due_time(): void
    {
        Http::fake(['https://example.com/flaky' => Http::response('boom', 503)]);

        $delivery = $this->makeFailingDelivery();
        $delivery = $this->app->make(DeliveryEngine::class)->send($delivery);

        Carbon::setTestNow($delivery->next_retry_at->copy()->addSecond());

        // Two runs of the scheduler overlapping (a slow queue, a stuck worker)
        // must not turn one planned attempt into two.
        $this->artisan('webhook-manager:dispatch-retries')->assertSuccessful();
        $attemptsAfterFirst = $delivery->fresh()->attempts;

        $this->artisan('webhook-manager:dispatch-retries')->assertSuccessful();

        $this->assertSame(
            $attemptsAfterFirst,
            $delivery->fresh()->attempts,
            'The same due retry ran twice; a claimed row must not be picked up again until it reschedules itself.'
        );

        Carbon::setTestNow();
    }

    public function test_retries_stop_at_max_attempts_instead_of_looping(): void
    {
        config()->set('webhook-manager.retry.max_attempts', 2);
        config()->set('webhook-manager.retry.base_delay_seconds', 1);

        Http::fake(['https://example.com/flaky' => Http::response('boom', 503)]);

        $delivery = $this->makeFailingDelivery();
        $delivery = $this->app->make(DeliveryEngine::class)->send($delivery);

        $this->assertNotNull($delivery->next_retry_at);

        Carbon::setTestNow($delivery->next_retry_at->copy()->addSecond());
        $this->artisan('webhook-manager:dispatch-retries')->assertSuccessful();

        $delivery = $delivery->fresh();

        $this->assertSame(2, $delivery->attempts);
        $this->assertSame(Delivery::STATUS_FAILED, $delivery->status);
        $this->assertNull($delivery->next_retry_at, 'A delivery out of attempts must not stay queued for a retry that will never be allowed.');

        Carbon::setTestNow();
    }

    public function test_the_command_is_registered_on_the_scheduler(): void
    {
        // Without a scheduler entry the command exists and nobody runs it,
        // which is the exact shape of the original defect one level up.
        $events = collect(app(Schedule::class)->events())
            ->map(fn ($event) => $event->command ?? $event->description)
            ->filter();

        $this->assertTrue(
            $events->contains(fn ($command) => str_contains((string) $command, 'webhook-manager:dispatch-retries')),
            'webhook-manager:dispatch-retries is not scheduled: '.$events->implode(', ')
        );
    }
}
