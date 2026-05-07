<?php

namespace Goldnead\WebhookManager\Triggers;

use Goldnead\WebhookManager\Contracts\TriggerInterface;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;
use Goldnead\WebhookManager\Support\StatamicSnapshot;

class EntryPublishedTrigger implements TriggerInterface
{
    public function handle(): string
    {
        return 'entry.published';
    }

    public function label(): string
    {
        return 'Entry — published';
    }

    public function sourceType(): string
    {
        return 'entry';
    }

    public function build(mixed $source, array $context = []): TriggerEvent
    {
        $payload = StatamicSnapshot::entry($source);

        return new TriggerEvent(
            triggerHandle: $this->handle(),
            sourceType: $this->sourceType(),
            sourceReference: $payload['id'] ?? null,
            payload: $payload,
            site: $payload['site'] ?? null,
            locale: $payload['locale'] ?? null,
            isReplay: (bool) ($context['replay'] ?? false),
            eventAt: new \DateTimeImmutable(),
        );
    }
}
