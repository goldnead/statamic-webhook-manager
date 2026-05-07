<?php

namespace Goldnead\WebhookManager\Mappers;

class DefaultValueResolver
{
    public function resolve(mixed $default): mixed
    {
        if (is_string($default)) {
            return match ($default) {
                '@now' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                '@uuid' => (string) \Illuminate\Support\Str::uuid(),
                default => $default,
            };
        }
        return $default;
    }
}
