<?php

namespace Goldnead\WebhookManager\Tests\Unit\Services;

use Goldnead\WebhookManager\Services\FailureClassifier;
use PHPUnit\Framework\TestCase;

class FailureClassifierTest extends TestCase
{
    private FailureClassifier $classifier;

    protected function setUp(): void
    {
        $this->classifier = new FailureClassifier();
    }

    public function test_network_error(): void
    {
        $type = $this->classifier->classify([
            'ok' => false,
            'error_kind' => 'network',
            'error_message' => 'Could not resolve host',
        ]);
        $this->assertSame(FailureClassifier::NETWORK, $type);
    }

    public function test_timeout_inferred_from_message(): void
    {
        $type = $this->classifier->classify([
            'ok' => false,
            'error_kind' => 'network',
            'error_message' => 'Operation timed out after 15000 ms',
        ]);
        $this->assertSame(FailureClassifier::TIMEOUT, $type);
    }

    public function test_auth_for_401_and_403(): void
    {
        $this->assertSame(FailureClassifier::AUTH, $this->classifier->classify(['ok' => true, 'status' => 401]));
        $this->assertSame(FailureClassifier::AUTH, $this->classifier->classify(['ok' => true, 'status' => 403]));
    }

    public function test_client_error_for_other_4xx(): void
    {
        $this->assertSame(FailureClassifier::CLIENT, $this->classifier->classify(['ok' => true, 'status' => 422]));
    }

    public function test_server_error_for_5xx(): void
    {
        $this->assertSame(FailureClassifier::SERVER, $this->classifier->classify(['ok' => true, 'status' => 503]));
    }
}
