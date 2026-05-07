<?php

namespace Goldnead\WebhookManager\Mappers;

class ValueTransformer
{
    /**
     * @param  string|array{name:string, ...mixed}  $rule
     */
    public function apply(string|array $rule, mixed $value): mixed
    {
        $name = is_string($rule) ? $rule : ($rule['name'] ?? null);
        if (! $name) {
            return $value;
        }

        return match ($name) {
            'trim' => is_string($value) ? trim($value) : $value,
            'lowercase' => is_string($value) ? mb_strtolower($value) : $value,
            'uppercase' => is_string($value) ? mb_strtoupper($value) : $value,
            'explode' => is_string($value) ? array_map('trim', explode(is_array($rule) ? ($rule['separator'] ?? ',') : ',', $value)) : $value,
            'implode' => is_array($value) ? implode(is_array($rule) ? ($rule['glue'] ?? ',') : ',', $value) : $value,
            'date_parse' => $this->parseDate($value, is_array($rule) ? ($rule['format'] ?? null) : null),
            default => $value,
        };
    }

    protected function parseDate(mixed $value, ?string $format): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            $dt = $format ? \DateTimeImmutable::createFromFormat($format, $value) : new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
        return $dt ? $dt->format(\DateTimeInterface::ATOM) : null;
    }
}
