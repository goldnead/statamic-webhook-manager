<?php

namespace Goldnead\WebhookManager\Auth\Verifiers;

use Goldnead\WebhookManager\Contracts\AuthVerifierInterface;
use Illuminate\Http\Request;

class NoAuthVerifier implements AuthVerifierInterface
{
    public function handle(): string
    {
        return 'none';
    }

    public function label(): string
    {
        return 'No authentication';
    }

    public function verify(Request $request, array $config): bool
    {
        return true;
    }

    public function sign(array $request, array $config): array
    {
        return $request;
    }
}
