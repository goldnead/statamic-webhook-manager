<?php

namespace Goldnead\WebhookManager\ValueObjects;

/**
 * Runtime context handed to template renderers, condition evaluators
 * and action executors. Wraps the trigger plus convenience accessors.
 */
final class ExecutionContext
{
    public function __construct(
        public readonly TriggerEvent $event,
        public readonly array $extra = [],
    ) {
    }

    public function payload(): array
    {
        return $this->event->payload;
    }

    public function field(string $dottedKey, mixed $default = null): mixed
    {
        $segments = explode('.', $dottedKey);
        $value = $this->event->payload;
        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
                continue;
            }
            return $default;
        }
        return $value;
    }

    public function with(array $extra): self
    {
        return new self($this->event, array_merge($this->extra, $extra));
    }
}
