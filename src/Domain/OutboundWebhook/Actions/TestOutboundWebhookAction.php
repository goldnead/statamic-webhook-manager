<?php

namespace Goldnead\WebhookManager\Domain\OutboundWebhook\Actions;

use Goldnead\WebhookManager\Domain\Delivery\Actions\CreateDeliverySnapshotAction;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Services\DeliveryEngine;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;

/**
 * Fire a one-off test request synchronously and return the resulting
 * Delivery so the CP can show the result inline.
 */
class TestOutboundWebhookAction
{
    public function __construct(
        protected CreateDeliverySnapshotAction $snapshotAction,
        protected DeliveryEngine $engine,
    ) {
    }

    public function __invoke(OutboundWebhook $hook, array $samplePayload = []): Delivery
    {
        $event = new TriggerEvent(
            triggerHandle: $hook->trigger_type,
            sourceType: 'test',
            sourceReference: 'test-'.now()->timestamp,
            payload: $samplePayload,
            site: null,
            locale: null,
            isReplay: false,
            eventAt: new \DateTimeImmutable(),
        );

        $context = new ExecutionContext($event);
        $delivery = ($this->snapshotAction)($hook, $context);

        return $this->engine->send($delivery);
    }
}
