<?php

namespace Goldnead\WebhookManager\Tests\Unit;

use Goldnead\WebhookManager\Auth\Verifiers\BasicAuthVerifier;
use Goldnead\WebhookManager\Tests\TestCase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * An endpoint saved with auth_type `basic` but no (or a half-filled)
 * auth_config used to authenticate everybody: hash_equals('', '') is true,
 * and an anonymous request has an empty user and an empty password, so both
 * comparisons passed and the request reached the action dispatcher.
 *
 * This matters more now that the inbound route no longer sits behind the
 * `web` group — the verifier is the only thing standing in front of it.
 */
class BasicAuthVerifierFailsClosedTest extends TestCase
{
    private function request(?string $user = null, ?string $pass = null): Request
    {
        $server = $user === null ? [] : [
            'PHP_AUTH_USER' => $user,
            'PHP_AUTH_PW' => (string) $pass,
        ];

        return Request::create('/webhooks/inbound/x', 'POST', [], [], [], $server);
    }

    public static function unconfigured(): array
    {
        return [
            'empty config' => [[]],
            'empty strings' => [['username' => '', 'password' => '']],
            'username only' => [['username' => 'someone', 'password' => '']],
            'password only' => [['username' => '', 'password' => 'something']],
            'null values' => [['username' => null, 'password' => null]],
        ];
    }

    #[DataProvider('unconfigured')]
    public function test_an_unconfigured_endpoint_rejects_an_anonymous_request(array $config): void
    {
        $this->assertFalse((new BasicAuthVerifier)->verify($this->request(), $config));
    }

    #[DataProvider('unconfigured')]
    public function test_an_unconfigured_endpoint_rejects_empty_credentials(array $config): void
    {
        $this->assertFalse((new BasicAuthVerifier)->verify($this->request('', ''), $config));
    }

    public function test_a_configured_endpoint_still_accepts_the_right_credentials(): void
    {
        $config = ['username' => 'hook', 'password' => 'test-'.bin2hex(random_bytes(8))];

        $this->assertTrue(
            (new BasicAuthVerifier)->verify($this->request('hook', $config['password']), $config),
        );
    }

    public function test_a_configured_endpoint_rejects_the_wrong_password(): void
    {
        $config = ['username' => 'hook', 'password' => 'test-'.bin2hex(random_bytes(8))];

        $this->assertFalse(
            (new BasicAuthVerifier)->verify($this->request('hook', 'test-wrong'), $config),
        );
    }
}
