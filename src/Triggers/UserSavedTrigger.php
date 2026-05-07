<?php

namespace Goldnead\WebhookManager\Triggers;

use Goldnead\WebhookManager\Contracts\TriggerInterface;
use Goldnead\WebhookManager\Support\StatamicSnapshot;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;

class UserSavedTrigger implements TriggerInterface
{
    public function handle(): string
    {
        return 'user.saved';
    }

    public function label(): string
    {
        return 'User — saved';
    }

    public function sourceType(): string
    {
        return 'user';
    }

    public function build(mixed $source, array $context = []): TriggerEvent
    {
        $payload = StatamicSnapshot::user($source);

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
