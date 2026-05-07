<?php

namespace Goldnead\WebhookManager\Contracts;

use Illuminate\Http\Request;

interface AuthVerifierInterface
{
    public function handle(): string;

    public function label(): string;

    /**
     * Verify an inbound HTTP request against the supplied auth config.
     *
     * @return bool true if authenticated, false otherwise. Implementations
     *              should NOT throw on auth failure; they should return
     *              false so the caller can render a uniform 401 response.
     */
    public function verify(Request $request, array $config): bool;

    /**
     * Apply auth headers/signatures to an outbound HTTP request payload.
     * Returns the augmented array of headers + body.
     *
     * @param  array{method: string, url: string, headers: array<string,string>, body: string}  $request
     * @return array{method: string, url: string, headers: array<string,string>, body: string}
     */
    public function sign(array $request, array $config): array;
}
