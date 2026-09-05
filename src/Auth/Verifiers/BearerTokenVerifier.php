<?php

namespace Goldnead\WebhookManager\Auth\Verifiers;

use Goldnead\WebhookManager\Contracts\AuthVerifierInterface;
use Illuminate\Http\Request;

class BearerTokenVerifier implements AuthVerifierInterface
{
    public function handle(): string
    {
        return 'bearer';
    }

    public function label(): string
    {
        return __('webhook-manager::messages.cp.auth_bearer');
    }

    public function verify(Request $request, array $config): bool
    {
        $expected = (string) ($config['token'] ?? '');
        $auth = (string) $request->header('Authorization', '');

        if (! str_starts_with($auth, 'Bearer ')) {
            return false;
        }
        $actual = substr($auth, 7);

        return $expected !== '' && hash_equals($expected, $actual);
    }

    public function sign(array $request, array $config): array
    {
        $token = $config['token'] ?? null;
        if ($token) {
            $request['headers']['Authorization'] = 'Bearer '.$token;
        }

        return $request;
    }
}
