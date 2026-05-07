<?php

namespace Goldnead\WebhookManager\Registries;

use Goldnead\WebhookManager\Auth\Verifiers\BasicAuthVerifier;
use Goldnead\WebhookManager\Auth\Verifiers\BearerTokenVerifier;
use Goldnead\WebhookManager\Auth\Verifiers\HmacSignatureVerifier;
use Goldnead\WebhookManager\Auth\Verifiers\NoAuthVerifier;
use Goldnead\WebhookManager\Auth\Verifiers\StaticHeaderVerifier;
use Goldnead\WebhookManager\Contracts\AuthVerifierInterface;

class AuthSchemeRegistry
{
    /** @var array<string, AuthVerifierInterface> */
    protected array $schemes = [];

    public function register(AuthVerifierInterface $verifier): void
    {
        $this->schemes[$verifier->handle()] = $verifier;
    }

    public function get(string $handle): ?AuthVerifierInterface
    {
        return $this->schemes[$handle] ?? null;
    }

    /** @return array<string, AuthVerifierInterface> */
    public function all(): array
    {
        return $this->schemes;
    }

    /** @return array<string, string> */
    public function options(): array
    {
        $opts = [];
        foreach ($this->schemes as $s) {
            $opts[$s->handle()] = $s->label();
        }
        return $opts;
    }

    public function registerDefaults(): void
    {
        $this->register(new NoAuthVerifier());
        $this->register(new StaticHeaderVerifier());
        $this->register(new BearerTokenVerifier());
        $this->register(new BasicAuthVerifier());
        $this->register(new HmacSignatureVerifier());
    }
}
