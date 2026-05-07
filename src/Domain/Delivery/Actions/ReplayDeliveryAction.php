<?php

namespace Goldnead\WebhookManager\Domain\Delivery\Actions;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Services\DeliveryEngine;
use Goldnead\WebhookManager\Services\Http\HttpRequestFactory;
use Goldnead\WebhookManager\Services\Logging\DeliveryLogger;
use Goldnead\WebhookManager\Templates\TemplateRenderer;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;

/**
 * Replay a stored Delivery — either using the original snapshot bytes
 * (default) or re-rendered against current data.
 *
 * TODO: REVIEW — re-render with current data is implemented as a flag,
 * but UI surfaces only "replay original" in v1. The flag is preserved for
 * the iterative CP polish.
 */
class ReplayDeliveryAction
{
    public function __construct(
        protected DeliveryEngine $engine,
        protected HttpRequestFactory $requestFactory,
        protected DeliveryLogger $logger,
        protected TemplateRenderer $renderer,
    ) {
    }

    public function __invoke(Delivery $delivery, bool $reRender = false): Delivery
    {
        $hook = $delivery->outboundWebhook;

        if ($reRender && $hook instanceof OutboundWebhook) {
            $event = new TriggerEvent(
                triggerHandle: $delivery->trigger_type,
                sourceType: $hook->triggerHandle(),
                sourceReference: $delivery->trigger_reference,
                payload: [],
                isReplay: true,
            );
            $context = new ExecutionContext($event);
            $request = $this->requestFactory->build($hook, $context);
            $delivery->request_url = $request['url'];
            $delivery->request_method = $request['method'];
            $delivery->request_body = $request['body'];
            $this->requestFactory->maskedSnapshot($request, $hook, $delivery);
            $delivery->rendered_from_snapshot = false;
        } else {
            $delivery->rendered_from_snapshot = true;
        }

        $delivery->status = Delivery::STATUS_PENDING;
        $delivery->error_type = null;
        $delivery->error_message = null;
        $delivery->next_retry_at = null;
        $delivery->save();

        $delivery = $this->engine->send($delivery);
        $this->logger->replayed($delivery);
        return $delivery;
    }
}
