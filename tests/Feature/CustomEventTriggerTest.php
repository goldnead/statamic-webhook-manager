<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Events\TriggerDetected;
use Goldnead\WebhookManager\Facades\WebhookManager;
use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Goldnead\WebhookManager\Tests\TestCase;
use Goldnead\WebhookManager\Triggers\CustomEventTrigger;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;

/**
 * A fake domain event used to exercise the config-driven custom event trigger.
 */
class FakeShipmentEvent
{
    public function __construct(
        public string $orderId,
        public array $items = [],
    ) {
    }
}

/**
 * A payload mapper referenced from config as an invokable class-string.
 */
class FakeShipmentPayload
{
    public function __invoke(FakeShipmentEvent $event): array
    {
        return [
            'id' => $event->orderId,
            'items' => $event->items,
        ];
    }
}

class CustomEventTriggerTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Configure a custom event trigger BEFORE the addon boots so the
        // service provider wires it up during bootAddon().
        $app['config']->set('webhook-manager.event_triggers', [
            'shipment.created' => [
                'event' => FakeShipmentEvent::class,
                'label' => 'Shipment — created',
                'source_type' => 'shipment',
                'payload' => FakeShipmentPayload::class,
            ],
        ]);
    }

    public function test_a_config_driven_event_fires_the_dispatch_pipeline_with_the_right_handle_and_payload(): void
    {
        // The configured trigger shows up in the registry (and thus the CP picker).
        $registry = $this->app->make(TriggerRegistry::class);
        $this->assertTrue($registry->has('shipment.created'));
        $this->assertArrayHasKey('shipment.created', $registry->options());

        // Capture the normalised TriggerDetected event that the generic
        // listener emits into the standard dispatch pipeline.
        $captured = [];
        $this->app->make('events')->listen(
            TriggerDetected::class,
            function (TriggerDetected $event) use (&$captured) {
                $captured[] = $event->trigger;
            },
        );

        // Dispatching the raw domain event drives the generic listener.
        event(new FakeShipmentEvent('ORDER-42', ['sku-1', 'sku-2']));

        $this->assertCount(1, $captured);

        /** @var TriggerEvent $triggerEvent */
        $triggerEvent = $captured[0];
        $this->assertSame('shipment.created', $triggerEvent->triggerHandle);
        $this->assertSame('shipment', $triggerEvent->sourceType);
        $this->assertSame('ORDER-42', $triggerEvent->sourceReference);
        $this->assertSame(
            ['id' => 'ORDER-42', 'items' => ['sku-1', 'sku-2']],
            $triggerEvent->payload,
        );
    }

    public function test_the_programmatic_api_registers_a_trigger_that_appears_in_the_registry(): void
    {
        WebhookManager::registerEventTrigger(FakeShipmentEvent::class, [
            'handle' => 'shipment.dispatched',
            'label' => 'Shipment — dispatched',
            'source_type' => 'shipment',
            'payload' => fn (FakeShipmentEvent $e) => ['id' => $e->orderId],
        ]);

        $registry = $this->app->make(TriggerRegistry::class);

        $this->assertTrue($registry->has('shipment.dispatched'));

        $trigger = $registry->get('shipment.dispatched');
        $this->assertInstanceOf(CustomEventTrigger::class, $trigger);
        $this->assertSame('Shipment — dispatched', $trigger->label());

        // Appears among the CP <select> options.
        $this->assertSame('Shipment — dispatched', $registry->options()['shipment.dispatched']);
    }
}
