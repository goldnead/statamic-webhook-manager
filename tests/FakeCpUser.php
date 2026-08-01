<?php

namespace Goldnead\WebhookManager\Tests;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Minimal stand-in for a Statamic CP user.
 *
 * The addon's controllers only ever ask `$request->user()?->can($ability)`,
 * so that is all this needs to answer — and answering it from an explicit
 * list (rather than from the Gate) is what lets a test assert what happens
 * for an ability nobody holds.
 */
class FakeCpUser implements Authenticatable
{
    /**
     * @param  array<int,string>  $abilities
     */
    public function __construct(
        protected array $abilities = [],
        public string $id = 'qa-user',
    ) {}

    public function can(string $ability, mixed $arguments = []): bool
    {
        return in_array($ability, $this->abilities, true);
    }

    public function cant(string $ability, mixed $arguments = []): bool
    {
        return ! $this->can($ability, $arguments);
    }

    public function getAuthIdentifierName()
    {
        return 'id';
    }

    public function getAuthIdentifier()
    {
        return $this->id;
    }

    public function getAuthPasswordName()
    {
        return 'password';
    }

    public function getAuthPassword()
    {
        return '';
    }

    public function getRememberToken()
    {
        return null;
    }

    public function setRememberToken($value) {}

    public function getRememberTokenName()
    {
        return null;
    }
}
