<?php

namespace Goldnead\WebhookManager\Domain\Delivery\Actions;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Services\Http\HttpRequestFactory;
use Goldnead\WebhookManager\Services\SubjectResolver;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;

/**
 * Build and persist a Delivery snapshot for a given hook + context.
 *
 * The snapshot is the canonical record of what we're about to send. It
 * is created BEFORE the request is executed so failures during execution
 * still leave an inspectable record in the CP.
 */
class CreateDeliverySnapshotAction
{
    public function __construct(
        protected HttpRequestFactory $requestFactory,
        protected SubjectResolver $subjects,
    ) {}

    public function __invoke(OutboundWebhook $hook, ExecutionContext $context): Delivery
    {
        $request = $this->requestFactory->build($hook, $context);

        // Resolved once, here, so the log can be read from the object's side
        // (see SubjectResolver). Null when nothing in the event names one.
        $subject = $this->subjects->resolve($context->event);

        $delivery = new Delivery([
            'outbound_webhook_id' => $hook->id,
            'trigger_type' => $context->event->triggerHandle,
            'trigger_reference' => $context->event->sourceReference,
            'subject_type' => $subject['type'] ?? null,
            'subject_id' => $subject['id'] ?? null,
            'status' => Delivery::STATUS_PENDING,
            'attempts' => 0,
            'correlation_id' => $context->event->correlationId,
            'request_url' => $request['url'],
            'request_method' => $request['method'],
            'request_headers' => [],
            'rendered_from_snapshot' => true,
        ]);

        $delivery->save();
        $this->requestFactory->maskedSnapshot($request, $hook, $delivery);
        $delivery->save();

        return $delivery;
    }
}
