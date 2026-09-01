<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Domain\Delivery\Actions\CreateDeliverySnapshotAction;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Facades\WebhookLog;
use Goldnead\WebhookManager\Tests\CpTestCase;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The delivery log, read from the object's side.
 *
 * A delivery row said what was sent and where, but not what it was about:
 * finding every attempt for payment 77 meant grepping request bodies. The
 * subject is resolved when the snapshot is written and then reachable three
 * ways — the `WebhookLog` facade for PHP, the listing's subject filter for
 * the operator, and the `for-subject` endpoint for a component on another
 * addon's page. Each path is covered here against the same seeded rows, so
 * the three answers cannot drift apart.
 */
class DeliverySubjectTest extends CpTestCase
{
    use RefreshDatabase;

    private function makeHook(): OutboundWebhook
    {
        return OutboundWebhook::create([
            'name' => 'Subject hook',
            'handle' => 'subject-hook-'.uniqid(),
            'enabled' => true,
            'trigger_type' => 'payments.paid',
            'url' => 'https://receiver.example.test/hook',
            'method' => 'POST',
            'auth_type' => 'none',
            'payload_type' => 'raw_json',
            'payload_template' => '{"x":1}',
            'queue_enabled' => false,
        ]);
    }

    private function makeDelivery(OutboundWebhook $hook, array $overrides = []): Delivery
    {
        return Delivery::create(array_merge([
            'outbound_webhook_id' => $hook->id,
            'trigger_type' => 'payments.paid',
            'status' => Delivery::STATUS_SUCCESS,
            'request_url' => 'https://receiver.example.test/hook',
            'request_method' => 'POST',
            'request_headers' => [],
            'request_body' => '{}',
            'response_status' => 200,
            'attempts' => 1,
        ], $overrides));
    }

    /** @return array{0: Delivery, 1: Delivery} the two rows about payment 77 */
    private function seedSubjects(): array
    {
        $hook = $this->makeHook();

        $a = $this->makeDelivery($hook, ['subject_type' => 'payment', 'subject_id' => '77']);
        $b = $this->makeDelivery($hook, ['subject_type' => 'payment', 'subject_id' => '77', 'status' => Delivery::STATUS_FAILED, 'response_status' => 500]);
        $this->makeDelivery($hook, ['subject_type' => 'offer', 'subject_id' => '77']);
        $this->makeDelivery($hook, ['subject_type' => 'payment', 'subject_id' => '78']);
        $this->makeDelivery($hook, []);

        return [$a, $b];
    }

    public function test_the_snapshot_action_stores_the_resolved_subject(): void
    {
        $hook = $this->makeHook();

        $event = new TriggerEvent(
            triggerHandle: 'payments.paid',
            sourceType: 'event',
            sourceReference: null,
            payload: ['payment_id' => 77, 'amount' => 1200],
        );

        $delivery = app(CreateDeliverySnapshotAction::class)($hook, new ExecutionContext($event));

        $this->assertSame('payment', $delivery->fresh()->subject_type);
        $this->assertSame('77', $delivery->fresh()->subject_id);
    }

    public function test_the_snapshot_action_leaves_the_subject_empty_when_nothing_names_one(): void
    {
        $hook = $this->makeHook();

        $event = new TriggerEvent(
            triggerHandle: 'custom.event',
            sourceType: 'event',
            sourceReference: null,
            payload: ['title' => 'Hello'],
        );

        $delivery = app(CreateDeliverySnapshotAction::class)($hook, new ExecutionContext($event));

        $this->assertNull($delivery->fresh()->subject_type);
        $this->assertNull($delivery->fresh()->subject_id);
    }

    public function test_the_facade_returns_only_the_rows_about_that_object(): void
    {
        [$a, $b] = $this->seedSubjects();

        $rows = WebhookLog::forSubject('payment', 77);

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $rows->pluck('id')->all());
        $this->assertSame(2, WebhookLog::countForSubject('payment', '77'));
        $this->assertSame(0, WebhookLog::countForSubject('payment', 79));
        $this->assertContains('payment', WebhookLog::subjectTypes());
    }

    public function test_the_listing_json_is_narrowed_by_the_subject_filter(): void
    {
        [$a, $b] = $this->seedSubjects();

        $response = $this->actingAs($this->superUser())
            ->getJson(cp_route('webhook-manager.deliveries.index', [
                'subject_type' => 'payment',
                'subject_id' => 77,
            ]));

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $ids);
        $this->assertSame(2, $response->json('meta.total'));

        $row = collect($response->json('data'))->firstWhere('id', $a->id);
        $this->assertSame('payment', $row['subject_type']);
        $this->assertSame('77', $row['subject_id']);
        $this->assertSame('payment #77', $row['subject']);
        $this->assertSame(__('webhook-manager::messages.subject_types.payment'), $row['subject_label']);
    }

    public function test_the_listing_page_carries_the_subject_props_and_column(): void
    {
        $this->seedSubjects();

        $response = $this->actingAs($this->superUser())
            ->withHeaders($this->inertiaHeaders())
            ->get(cp_route('webhook-manager.deliveries.index', [
                'subject_type' => 'payment',
                'subject_id' => 77,
            ]));

        $response->assertOk();

        $this->assertSame(['type' => 'payment', 'id' => '77'], $response->json('props.subjectFilter'));

        $types = collect($response->json('props.subjectTypes'));
        $this->assertContains('payment', $types->pluck('value')->all());
        $this->assertContains('offer', $types->pluck('value')->all());
        $this->assertNotEmpty($types->firstWhere('value', 'payment')['label']);

        $columns = collect($response->json('props.initialColumns'))->pluck('field')->all();
        $this->assertContains('subject', $columns);
    }

    public function test_the_for_subject_endpoint_answers_rows_and_total(): void
    {
        [$a, $b] = $this->seedSubjects();

        $response = $this->actingAs($this->superUser())
            ->getJson(cp_route('webhook-manager.deliveries.for-subject', [
                'subject_type' => 'payment',
                'subject_id' => '77',
                'limit' => 1,
            ]));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame(2, $response->json('total'));

        $row = $response->json('data.0');
        $this->assertContains($row['id'], [$a->id, $b->id]);
        $this->assertArrayHasKey('can_replay', $row);
        $this->assertArrayHasKey('replay_url', $row);
        $this->assertArrayHasKey('show_url', $row);
    }

    public function test_the_for_subject_endpoint_requires_the_view_permission(): void
    {
        $this->seedSubjects();

        $this->actingAs($this->cpUser([]))
            ->getJson(cp_route('webhook-manager.deliveries.for-subject', [
                'subject_type' => 'payment',
                'subject_id' => '77',
            ]))
            ->assertForbidden();
    }

    public function test_the_for_subject_endpoint_rejects_a_missing_subject(): void
    {
        $this->actingAs($this->superUser())
            ->getJson(cp_route('webhook-manager.deliveries.for-subject'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['subject_type', 'subject_id']);
    }

    public function test_the_for_subject_route_is_not_swallowed_by_the_show_route(): void
    {
        // Declared before `deliveries/{delivery}`; if it ever moves below, the
        // literal segment binds as a delivery id and this answers 404.
        $this->actingAs($this->superUser())
            ->getJson('/cp/webhook-manager/deliveries/for-subject?subject_type=payment&subject_id=1')
            ->assertOk();
    }
}
