<?php

namespace Goldnead\WebhookManager\Tests\Unit\Auth;

use Goldnead\WebhookManager\Auth\Support\SignatureGenerator;
use Goldnead\WebhookManager\Auth\Verifiers\HmacSignatureVerifier;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class HmacSignatureVerifierTest extends TestCase
{
    private function makeRequest(string $body, array $headers = []): Request
    {
        $r = Request::create('/inbound/test', 'POST', [], [], [], [], $body);
        foreach ($headers as $k => $v) {
            $r->headers->set($k, $v);
        }
        return $r;
    }

    public function test_verifies_a_valid_sha256_signature_without_timestamp(): void
    {
        $secret = 'super-secret';
        $body = '{"hello":"world"}';
        $signature = SignatureGenerator::compute($body, $secret);

        $request = $this->makeRequest($body, ['X-Webhook-Signature' => 'sha256='.$signature]);

        $verifier = new HmacSignatureVerifier();
        $this->assertTrue($verifier->verify($request, ['secret' => $secret]));
    }

    public function test_rejects_an_invalid_signature(): void
    {
        $request = $this->makeRequest('{"hello":"world"}', [
            'X-Webhook-Signature' => 'sha256=deadbeef',
        ]);
        $verifier = new HmacSignatureVerifier();
        $this->assertFalse($verifier->verify($request, ['secret' => 'super-secret']));
    }

    public function test_rejects_when_secret_is_missing(): void
    {
        $request = $this->makeRequest('{}', ['X-Webhook-Signature' => 'sha256=abc']);
        $verifier = new HmacSignatureVerifier();
        $this->assertFalse($verifier->verify($request, []));
    }

    public function test_signs_outbound_request_with_timestamp_and_signature(): void
    {
        $verifier = new HmacSignatureVerifier();
        $signed = $verifier->sign([
            'method' => 'POST',
            'url' => 'https://example.com',
            'headers' => [],
            'body' => '{"x":1}',
        ], ['secret' => 's3', 'algorithm' => 'sha256']);

        $this->assertArrayHasKey('X-Webhook-Signature', $signed['headers']);
        $this->assertArrayHasKey('X-Webhook-Timestamp', $signed['headers']);
        $this->assertStringStartsWith('sha256=', $signed['headers']['X-Webhook-Signature']);
    }
}
