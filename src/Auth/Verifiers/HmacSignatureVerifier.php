<?php

namespace Goldnead\WebhookManager\Auth\Verifiers;

use Goldnead\WebhookManager\Auth\Support\SignatureGenerator;
use Goldnead\WebhookManager\Contracts\AuthVerifierInterface;
use Illuminate\Http\Request;

/**
 * HMAC SHA256 (or SHA512) signature verification with optional timestamp
 * tolerance for replay protection.
 */
class HmacSignatureVerifier implements AuthVerifierInterface
{
    public function handle(): string
    {
        return 'hmac';
    }

    public function label(): string
    {
        return 'HMAC signature';
    }

    public function verify(Request $request, array $config): bool
    {
        $secret = (string) ($config['secret'] ?? '');
        if ($secret === '') {
            return false;
        }

        $algo = $config['algorithm'] ?? config('webhook-manager.security.default_hash_algorithm', 'sha256');
        $sigHeader = $config['signature_header']
            ?? config('webhook-manager.security.signature_header', 'X-Webhook-Signature');
        $tsHeader = $config['timestamp_header']
            ?? config('webhook-manager.security.timestamp_header', 'X-Webhook-Timestamp');
        $tolerance = (int) ($config['timestamp_tolerance_seconds']
            ?? config('webhook-manager.security.timestamp_tolerance_seconds', 300));
        $requireTimestamp = (bool) ($config['require_timestamp'] ?? false);

        $providedSignature = (string) $request->header($sigHeader, '');
        if ($providedSignature === '') {
            return false;
        }

        $body = (string) $request->getContent();
        $timestamp = (string) $request->header($tsHeader, '');

        if ($requireTimestamp) {
            if ($timestamp === '') {
                return false;
            }
            if ($tolerance > 0 && abs(time() - (int) $timestamp) > $tolerance) {
                return false;
            }
        }

        $payload = $timestamp !== '' ? ($timestamp.'.'.$body) : $body;
        $expected = SignatureGenerator::compute($payload, $secret, $algo);

        // Some providers prefix `sha256=`; tolerate both.
        if (str_contains($providedSignature, '=')) {
            $providedSignature = (string) substr($providedSignature, strpos($providedSignature, '=') + 1);
        }

        return hash_equals($expected, $providedSignature);
    }

    public function sign(array $request, array $config): array
    {
        $secret = (string) ($config['secret'] ?? '');
        if ($secret === '') {
            return $request;
        }
        $algo = $config['algorithm'] ?? 'sha256';
        $sigHeader = $config['signature_header']
            ?? config('webhook-manager.security.signature_header', 'X-Webhook-Signature');
        $tsHeader = $config['timestamp_header']
            ?? config('webhook-manager.security.timestamp_header', 'X-Webhook-Timestamp');

        $timestamp = (string) time();
        $payload = $timestamp.'.'.($request['body'] ?? '');
        $signature = SignatureGenerator::compute($payload, $secret, $algo);

        $request['headers'][$tsHeader] = $timestamp;
        $request['headers'][$sigHeader] = $algo.'='.$signature;

        return $request;
    }
}
