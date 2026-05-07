<?php

namespace Goldnead\WebhookManager\Auth\Support;

final class SignatureGenerator
{
    public static function compute(string $payload, string $secret, string $algorithm = 'sha256'): string
    {
        if (! in_array($algorithm, hash_hmac_algos(), true)) {
            throw new \InvalidArgumentException("Unsupported HMAC algorithm: {$algorithm}");
        }
        return hash_hmac($algorithm, $payload, $secret);
    }
}
