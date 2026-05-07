<?php

namespace Goldnead\WebhookManager\Mappers;

class TypeCoercer
{
    public function coerce(string $type, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        return match ($type) {
            'string' => is_scalar($value) ? (string) $value : (string) json_encode($value),
            'int', 'integer' => is_numeric($value) ? (int) $value : null,
            'float' => is_numeric($value) ? (float) $value : null,
            'bool', 'boolean' => $this->toBool($value),
            'array' => is_array($value) ? $value : [$value],
            default => $value,
        };
    }

    protected function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (float) $value !== 0.0;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'y', 'on'], true);
        }
        return (bool) $value;
    }
}
