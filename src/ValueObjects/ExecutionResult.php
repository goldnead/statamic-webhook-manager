<?php

namespace Goldnead\WebhookManager\ValueObjects;

/**
 * Simple success/failure result with an optional message and payload.
 */
final class ExecutionResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly string $message = '',
        public readonly array $data = [],
    ) {
    }

    public static function ok(string $message = '', array $data = []): self
    {
        return new self(true, $message, $data);
    }

    public static function fail(string $message, array $data = []): self
    {
        return new self(false, $message, $data);
    }
}
