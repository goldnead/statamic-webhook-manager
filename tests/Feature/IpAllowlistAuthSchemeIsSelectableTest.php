<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Goldnead\WebhookManager\Registries\AuthSchemeRegistry;
use Goldnead\WebhookManager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * `ip_allowlist` was accepted by SaveInboundEndpointRequest, given a badge
 * colour on the inbound index and an example config on the edit screen — and
 * never registered on AuthSchemeRegistry, so the endpoint rejected every
 * request with a 401 the operator could not explain.
 *
 * It failed closed, so this was a broken feature rather than an open door.
 * That is the only good thing about it.
 */
class IpAllowlistAuthSchemeIsSelectableTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_auth_type_the_form_accepts_is_a_registered_scheme(): void
    {
        // Same structural idea as PermissionStringsAreRegisteredTest: the
        // validator's allowlist and the registry must not be able to drift.
        $accepted = $this->authTypesAcceptedByTheForm();
        $registered = array_keys(app(AuthSchemeRegistry::class)->all());

        $this->assertNotEmpty($accepted, 'The auth_type Rule::in() list could not be read — the regex has gone stale.');

        $missing = array_values(array_diff($accepted, $registered));

        $this->assertSame([], $missing, 'Selectable but unregistered auth schemes reject every request: '.implode(', ', $missing));
    }

    public function test_an_allowed_ip_gets_through_and_a_foreign_one_does_not(): void
    {
        InboundEndpoint::create([
            'name' => 'Partner feed',
            'handle' => 'partner-feed',
            'enabled' => true,
            'path' => 'partner-feed',
            'allowed_methods' => ['POST'],
            'auth_type' => 'ip_allowlist',
            'auth_config' => ['ips' => ['10.1.2.3', '192.168.10.0/24']],
            'expected_content_type' => 'application/json',
            'max_payload_kb' => 64,
            'replay_protection_enabled' => false,
            'logging_mode' => 'partial',
            'mapping_config' => [],
            'action_type' => 'audit_log',
            'action_config' => [],
            'response_config' => null,
        ]);

        $this->post('/webhooks/inbound/partner-feed', ['ping' => true], [
            'REMOTE_ADDR' => '10.1.2.3',
        ])->assertStatus(200);

        $this->post('/webhooks/inbound/partner-feed', ['ping' => true], [
            'REMOTE_ADDR' => '192.168.10.77',
        ])->assertStatus(200);

        $this->post('/webhooks/inbound/partner-feed', ['ping' => true], [
            'REMOTE_ADDR' => '203.0.113.9',
        ])->assertStatus(401);
    }

    public function test_an_empty_allowlist_rejects_everything(): void
    {
        $verifier = new \Goldnead\WebhookManager\Auth\Verifiers\IpAllowlistVerifier();

        $this->assertFalse($verifier->verify(request(), []));
        $this->assertFalse($verifier->verify(request(), ['ips' => []]));
        $this->assertFalse($verifier->verify(request(), ['allow' => ['']]));
    }

    public function test_the_legacy_allow_key_still_works(): void
    {
        // Anything configured before this fix used `allow`, because that is
        // what the class read. Those endpoints must keep working.
        $verifier = new \Goldnead\WebhookManager\Auth\Verifiers\IpAllowlistVerifier();

        $request = \Illuminate\Http\Request::create('/', 'POST', server: ['REMOTE_ADDR' => '10.0.0.5']);

        $this->assertTrue($verifier->verify($request, ['allow' => ['10.0.0.5']]));
        $this->assertTrue($verifier->verify($request, ['ips' => ['10.0.0.5']]));
    }

    /** @return array<int, string> */
    protected function authTypesAcceptedByTheForm(): array
    {
        $source = (string) file_get_contents(__DIR__.'/../../src/Http/Requests/SaveInboundEndpointRequest.php');

        if (! preg_match("/'auth_type'\s*=>\s*\[[^\]]*Rule::in\(\[([^\]]*)\]\)/s", $source, $m)) {
            return [];
        }

        preg_match_all("/'([^']+)'/", $m[1], $types);

        return $types[1];
    }
}
