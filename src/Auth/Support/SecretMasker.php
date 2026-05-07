<?php

namespace Goldnead\WebhookManager\Auth\Support;

/**
 * Masks secrets and sensitive header values for safe display in CP and logs.
 *
 * The full secret is never returned by this class. Use the "replace flow"
 * (delete and re-enter) when a secret needs rotation, never a "reveal flow".
 */
final class SecretMasker
{
    /**
     * Returns "abcd…wxyz" for short visibility plus length indicator.
     */
    public static function mask(?string $secret): string
    {
        $secret = (string) $secret;
        if ($secret === '') {
            return '';
        }
        $len = strlen($secret);
        if ($len <= 8) {
            return str_repeat('•', $len);
        }
        return substr($secret, 0, 4).str_repeat('•', max(4, $len - 8)).substr($secret, -4);
    }

    /**
     * Mask sensitive header values by name. Names are matched case-insensitively.
     *
     * @param  array<string,string|array<string>>  $headers
     * @param  array<int,string>  $maskNames
     * @return array<string,string|array<string>>
     */
    public static function maskHeaders(array $headers, array $maskNames): array
    {
        $maskLowered = array_map('strtolower', $maskNames);
        $out = [];
        foreach ($headers as $name => $value) {
            if (in_array(strtolower((string) $name), $maskLowered, true)) {
                $out[$name] = is_array($value)
                    ? array_map(fn ($v) => self::mask((string) $v), $value)
                    : self::mask((string) $value);
                continue;
            }
            $out[$name] = $value;
        }
        return $out;
    }

    /**
     * Mask payload-level keys recursively.
     *
     * @param  array<int,string>  $maskKeys
     */
    public static function maskPayload(mixed $payload, array $maskKeys): mixed
    {
        if (! is_array($payload)) {
            return $payload;
        }
        $maskLowered = array_map('strtolower', $maskKeys);
        $out = [];
        foreach ($payload as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $maskLowered, true)) {
                $out[$key] = is_string($value) ? self::mask($value) : '«masked»';
                continue;
            }
            $out[$key] = is_array($value) ? self::maskPayload($value, $maskKeys) : $value;
        }
        return $out;
    }
}
