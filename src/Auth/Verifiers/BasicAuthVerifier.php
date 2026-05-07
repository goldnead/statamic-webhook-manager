<?php

namespace Goldnead\WebhookManager\Auth\Verifiers;

use Goldnead\WebhookManager\Contracts\AuthVerifierInterface;
use Illuminate\Http\Request;

class BasicAuthVerifier implements AuthVerifierInterface
{
    public function handle(): string
    {
        return 'basic';
    }

    public function label(): string
    {
        return 'Basic auth';
    }

    public function verify(Request $request, array $config): bool
    {
        $expectedUser = (string) ($config['username'] ?? '');
        $expectedPass = (string) ($config['password'] ?? '');
        $user = (string) $request->getUser();
        $pass = (string) $request->getPassword();

        return hash_equals($expectedUser, $user) && hash_equals($expectedPass, $pass);
    }

    public function sign(array $request, array $config): array
    {
        $user = (string) ($config['username'] ?? '');
        $pass = (string) ($config['password'] ?? '');
        if ($user !== '' || $pass !== '') {
            $request['headers']['Authorization'] = 'Basic '.base64_encode($user.':'.$pass);
        }
        return $request;
    }
}
