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
        return __('webhook-manager::messages.cp.auth_basic');
    }

    public function verify(Request $request, array $config): bool
    {
        $expectedUser = (string) ($config['username'] ?? '');
        $expectedPass = (string) ($config['password'] ?? '');

        // Fail closed on an unconfigured endpoint. Without this guard an
        // endpoint saved as `basic` with an empty auth_config authenticated
        // *everyone*: hash_equals('', '') is true, and an anonymous request
        // has an empty user and an empty password — so both comparisons
        // passed and the request reached the action dispatcher.
        if ($expectedUser === '' || $expectedPass === '') {
            return false;
        }

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
