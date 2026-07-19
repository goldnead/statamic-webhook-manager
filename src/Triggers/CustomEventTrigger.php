<?php

namespace Goldnead\WebhookManager\Triggers;

use Goldnead\WebhookManager\Contracts\TriggerInterface;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;

/**
 * A configurable trigger that wraps ANY Laravel/Statamic event class.
 *
 * Unlike the built-in triggers (EntrySaved, FormSubmitted, …) which are
 * hardcoded to specific Statamic events, this trigger is data-driven: its
 * handle, label, source type and payload mapping are supplied at
 * registration time, either from the `webhook-manager.event_triggers`
 * config section or via WebhookManager::registerEventTrigger().
 *
 * The generic listener wired up alongside it (see WebhookManager) turns the
 * raw event object into a normalised TriggerEvent by calling build().
 */
class CustomEventTrigger implements TriggerInterface
{
    /**
     * @param  string  $handle  Unique trigger handle, e.g. "order.shipped".
     * @param  string  $label  Human-readable label for the CP picker.
     * @param  string  $sourceType  Source category, e.g. "order" or "event".
     * @param  (callable(mixed): array)|null  $payloadResolver  Maps the raw
     *         event object to an array payload. When null, build() falls back
     *         to toArray()/public properties/array pass-through.
     * @param  string|null  $description  Optional human description.
     */
    public function __construct(
        protected string $handle,
        protected string $label,
        protected string $sourceType = 'event',
        protected mixed $payloadResolver = null,
        protected ?string $description = null,
    ) {
    }

    public function handle(): string
    {
        return $this->handle;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function sourceType(): string
    {
        return $this->sourceType;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function build(mixed $source, array $context = []): TriggerEvent
    {
        $payload = $this->resolvePayload($source);

        return new TriggerEvent(
            triggerHandle: $this->handle,
            sourceType: $this->sourceType,
            sourceReference: $payload['id'] ?? $payload['reference'] ?? null,
            payload: $payload,
            site: $payload['site'] ?? null,
            locale: $payload['locale'] ?? null,
            isReplay: (bool) ($context['replay'] ?? false),
            eventAt: new \DateTimeImmutable(),
        );
    }

    /**
     * Turn the raw event into an array payload.
     *
     * Precedence:
     *   1. explicit payload resolver (callable/invokable class)
     *   2. the event is already an array → pass through
     *   3. the event exposes ->toArray() → use it
     *   4. serialize the event's public properties
     *   5. wrap a scalar under a "value" key
     */
    protected function resolvePayload(mixed $source): array
    {
        if ($this->payloadResolver !== null) {
            return (array) ($this->payloadResolver)($source);
        }

        if (is_array($source)) {
            return $source;
        }

        if (is_object($source)) {
            if (method_exists($source, 'toArray')) {
                return (array) $source->toArray();
            }

            return get_object_vars($source);
        }

        return ['value' => $source];
    }
}
