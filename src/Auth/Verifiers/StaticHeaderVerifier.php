<?php

namespace Goldnead\WebhookManager\Auth\Verifiers;

use Goldnead\WebhookManager\Contracts\AuthVerifierInterface;
use Illuminate\Http\Request;

/**
 * Static custom-header secret. Useful for many SaaS webhook providers.
 */
class StaticHeaderVerifier implements AuthVerifierInterface
{
    public function handle(): string
    {
        return 'static_header';
    }

    public function label(): string
    {
        return 'Static header secret';
    }

    public function verify(Request $request, array $config): bool
    {
        $name = $config['header_name'] ?? null;
        $expected = $config['secret'] ?? null;
        if (! $name || ! $expected) {
            return false;
        }

        $actual = (string) $request->header($name, '');
        return hash_equals((string) $expected, $actual);
    }

    public function sign(array $request, array $config): array
    {
        $name = $config['header_name'] ?? 'X-Webhook-Secret';
        $secret = $config['secret'] ?? null;
        if ($secret) {
            $request['headers'][$name] = $secret;
        }
        return $request;
    }
}
