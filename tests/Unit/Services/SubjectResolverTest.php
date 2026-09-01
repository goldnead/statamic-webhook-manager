<?php

namespace Goldnead\WebhookManager\Tests\Unit\Services;

use Goldnead\WebhookManager\Services\SubjectResolver;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;
use PHPUnit\Framework\TestCase;

/**
 * Which object a trigger event was about.
 *
 * The delivery log had one row per attempt and no way back to the payment,
 * offer or entry the attempt concerned. The resolver fills that in once, at
 * snapshot time, from four sources in a fixed order. Each test here pins one
 * rung of that ladder, and the two null cases pin where the ladder ends —
 * a resolver that guessed a subject where there is none would file
 * deliveries under an object they were not about.
 */
class SubjectResolverTest extends TestCase
{
    private SubjectResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new SubjectResolver([
            'payment' => ['keys' => ['payment_id', 'payment.id'], 'triggers' => ['payment.*', 'payments.*']],
            'offer' => ['keys' => ['offer_id', 'offer.id'], 'triggers' => ['offer.*', 'offers.*']],
        ]);
    }

    public function test_an_explicit_subject_pair_in_the_payload_wins_over_everything(): void
    {
        $event = $this->event('payments.paid', 'payment', '99', [
            'subject_type' => 'Offer',
            'subject_id' => 12,
            'payment_id' => 77,
        ]);

        $this->assertSame(['type' => 'offer', 'id' => '12'], $this->resolver->resolve($event));
    }

    public function test_a_configured_key_in_the_payload_resolves_the_type(): void
    {
        $event = $this->event('custom.event', 'event', null, ['payment_id' => 77]);

        $this->assertSame(['type' => 'payment', 'id' => '77'], $this->resolver->resolve($event));
    }

    public function test_a_dotted_key_reaches_into_a_nested_payload(): void
    {
        $event = $this->event('custom.event', 'event', null, ['payment' => ['id' => 'pay_abc']]);

        $this->assertSame(['type' => 'payment', 'id' => 'pay_abc'], $this->resolver->resolve($event));
    }

    public function test_a_matching_trigger_pattern_uses_the_source_reference(): void
    {
        $event = $this->event('payments.paid', 'event', '4711', ['amount' => 12.5]);

        $this->assertSame(['type' => 'payment', 'id' => '4711'], $this->resolver->resolve($event));
    }

    public function test_a_matching_trigger_pattern_falls_back_to_the_payload_id(): void
    {
        $event = $this->event('offer.accepted', 'event', null, ['id' => 5]);

        $this->assertSame(['type' => 'offer', 'id' => '5'], $this->resolver->resolve($event));
    }

    public function test_built_in_triggers_resolve_from_the_event_source(): void
    {
        $event = $this->event('entry.saved', 'entry', 'entry-uuid-1', ['title' => 'Hello']);

        $this->assertSame(['type' => 'entry', 'id' => 'entry-uuid-1'], $this->resolver->resolve($event));
    }

    public function test_nothing_to_go_on_resolves_to_null(): void
    {
        $event = $this->event('custom.event', 'event', null, ['title' => 'Hello']);

        $this->assertNull($this->resolver->resolve($event));
    }

    public function test_the_generic_event_source_type_is_not_a_subject(): void
    {
        // `event` is what custom event triggers default to; with a reference
        // but no configured match it must not be filed as type "event".
        $event = $this->event('custom.event', 'event', 'ref-1', []);

        $this->assertNull($this->resolver->resolve($event));
    }

    public function test_an_empty_source_type_is_not_a_subject(): void
    {
        $event = $this->event('custom.event', '', 'ref-1', []);

        $this->assertNull($this->resolver->resolve($event));
    }

    public function test_type_and_id_are_normalised_and_bounded(): void
    {
        $event = $this->event('custom.event', 'event', null, [
            'subject_type' => '  PayMent ',
            'subject_id' => str_repeat('x', 80),
        ]);

        $subject = $this->resolver->resolve($event);

        $this->assertSame('payment', $subject['type']);
        $this->assertSame(64, strlen($subject['id']));
    }

    public function test_empty_and_non_scalar_values_are_skipped(): void
    {
        $event = $this->event('custom.event', 'event', null, [
            'subject_type' => 'payment',
            'subject_id' => '',
            'payment_id' => ['nested' => 1],
            'offer_id' => '  ',
        ]);

        $this->assertNull($this->resolver->resolve($event));
    }

    private function event(string $handle, string $sourceType, ?string $reference, array $payload): TriggerEvent
    {
        return new TriggerEvent(
            triggerHandle: $handle,
            sourceType: $sourceType,
            sourceReference: $reference,
            payload: $payload,
        );
    }
}
