<?php

namespace Goldnead\WebhookManager\Contracts;

use Goldnead\WebhookManager\ValueObjects\TriggerEvent;

/**
 * A trigger represents a normalized internal event that can fire webhooks
 * or rules. Listeners convert raw Statamic/Laravel events into a uniform
 * TriggerEvent payload.
 */
interface TriggerInterface
{
    /** Unique handle, e.g. "entry.published". */
    public function handle(): string;

    /** Human-readable label for the CP. */
    public function label(): string;

    /** Source type, e.g. "entry", "form", "user". */
    public function sourceType(): string;

    /** Build the trigger event from a raw source object. */
    public function build(mixed $source, array $context = []): TriggerEvent;
}
