<?php

namespace Goldnead\WebhookManager\Services;

/**
 * Map raw HTTP results to a stable error_type label used in the CP and
 * by the RetryPlanner.
 */
class FailureClassifier
{
    public const NETWORK = 'network';
    public const TIMEOUT = 'timeout';
    public const AUTH = 'auth';
    public const CLIENT = 'client';
    public const SERVER = 'server';
    public const PAYLOAD = 'payload';
    public const CONFIGURATION = 'configuration';
    public const INTERNAL = 'internal';

    /**
     * @param  array{ok?:bool, status?:?int, error_kind?:?string, error_message?:?string}  $response
     */
    public function classify(array $response): string
    {
        if (($response['ok'] ?? false) === false) {
            $kind = $response['error_kind'] ?? null;
            $message = strtolower((string) ($response['error_message'] ?? ''));
            if ($kind === 'network') {
                return str_contains($message, 'timed out') || str_contains($message, 'timeout')
                    ? self::TIMEOUT
                    : self::NETWORK;
            }
            return self::INTERNAL;
        }

        $status = (int) ($response['status'] ?? 0);
        return match (true) {
            $status === 401 || $status === 403 => self::AUTH,
            $status === 408 || $status === 504 => self::TIMEOUT,
            $status >= 400 && $status < 500 => self::CLIENT,
            $status >= 500 => self::SERVER,
            default => self::INTERNAL,
        };
    }
}
