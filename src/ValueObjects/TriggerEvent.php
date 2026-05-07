<?php

namespace Goldnead\WebhookManager\ValueObjects;

use Illuminate\Support\Str;

/**
 * Normalized internal trigger event.
 *
 * Listeners convert framework events into this object so the rest of the
 * system never has to special-case the source.
 */
final class TriggerEvent
{
    public readonly string $correlationId;

    public function __construct(
        public readonly string $triggerHandle,
        public readonly string $sourceType,
        public readonly ?string $sourceReference,
        public readonly array $payload,
        public readonly ?string $site = null,
        public readonly ?string $locale = null,
        public readonly bool $isReplay = false,
        public readonly ?\DateTimeImmutable $eventAt = null,
        ?string $correlationId = null,
    ) {
        $this->correlationId = $correlationId ?? (string) Str::uuid();
    }

    public function toArray(): array
    {
        return [
            'trigger' => $this->triggerHandle,
            'source_type' => $this->sourceType,
            'source_reference' => $this->sourceReference,
            'payload' => $this->payload,
            'site' => $this->site,
            'locale' => $this->locale,
            'replay' => $this->isReplay,
            'event_at' => $this->eventAt?->format(\DateTimeInterface::ATOM),
            'correlation_id' => $this->correlationId,
        ];
    }
}
