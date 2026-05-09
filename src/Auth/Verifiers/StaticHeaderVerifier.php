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
        // The CP edit form (resources/js/pages/inbound/Edit.vue) prompts
        // for `{ "header": "...", "value": "..." }` and that's also the
        // schema the seed examples and feature tests use.
        $name = $config['header'] ?? $config['header_name'] ?? null;
        $expected = $config['value'] ?? $config['secret'] ?? null;
        if (! $name || ! $expected) {
            return false;
        }

        $actual = (string) $request->header($name, '');
        return hash_equals((string) $expected, $actual);
    }

    public function sign(array $request, array $config): array
    {
        $name = $config['header'] ?? $config['header_name'] ?? 'X-Webhook-Secret';
        $secret = $config['value'] ?? $config['secret'] ?? null;
        if ($secret) {
            $request['headers'][$name] = $secret;
        }
        return $request;
    }
}
