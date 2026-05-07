<?php

namespace Goldnead\WebhookManager\Mappers;

/**
 * Resolves dot-notation paths through nested arrays, supporting `[index]`
 * for array access — e.g. `contacts[0].email`.
 */
class PathResolver
{
    public function get(array $payload, string $path): mixed
    {
        $segments = $this->parse($path);
        $value = $payload;

        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
                continue;
            }
            return null;
        }
        return $value;
    }

    /** @return array<int, string|int> */
    protected function parse(string $path): array
    {
        // Replace `[N]` with `.N` then split on dots.
        $path = preg_replace('/\[(\d+)\]/', '.$1', $path) ?? $path;
        $parts = array_filter(explode('.', $path), fn ($p) => $p !== '');
        $out = [];
        foreach ($parts as $p) {
            $out[] = is_numeric($p) ? (int) $p : (string) $p;
        }
        return $out;
    }
}
