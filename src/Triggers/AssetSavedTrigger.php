<?php

namespace Goldnead\WebhookManager\Triggers;

use Goldnead\WebhookManager\Contracts\TriggerInterface;
use Goldnead\WebhookManager\Support\StatamicSnapshot;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;

class AssetSavedTrigger implements TriggerInterface
{
    public function handle(): string
    {
        return 'asset.saved';
    }

    public function label(): string
    {
        return 'Asset — saved';
    }

    public function sourceType(): string
    {
        return 'asset';
    }

    public function build(mixed $source, array $context = []): TriggerEvent
    {
        $payload = StatamicSnapshot::asset($source);

        return new TriggerEvent(
            triggerHandle: $this->handle(),
            sourceType: $this->sourceType(),
            sourceReference: $payload['id'] ?? null,
            payload: $payload,
            site: null,
            locale: null,
            isReplay: (bool) ($context['replay'] ?? false),
            eventAt: new \DateTimeImmutable(),
        );
    }
}
