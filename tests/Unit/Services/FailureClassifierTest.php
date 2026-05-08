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

    public function test_classify_exception_invalid_argument_is_payload(): void
    {
        $type = $this->classifier->classifyException(new \InvalidArgumentException('bad input'));
        $this->assertSame(FailureClassifier::PAYLOAD, $type);
    }

    public function test_classify_exception_type_error_is_payload(): void
    {
        $type = $this->classifier->classifyException(new \TypeError('expected string, got int'));
        $this->assertSame(FailureClassifier::PAYLOAD, $type);
    }

    public function test_classify_exception_value_error_is_payload(): void
    {
        $type = $this->classifier->classifyException(new \ValueError('value out of range'));
        $this->assertSame(FailureClassifier::PAYLOAD, $type);
    }

    public function test_classify_exception_json_exception_is_payload(): void
    {
        $type = $this->classifier->classifyException(new \JsonException('Syntax error'));
        $this->assertSame(FailureClassifier::PAYLOAD, $type);
    }

    public function test_classify_exception_default_runtime_is_internal(): void
    {
        $type = $this->classifier->classifyException(new \RuntimeException('boom'));
        $this->assertSame(FailureClassifier::INTERNAL, $type);
    }

    public function test_classify_exception_logic_exception_is_internal(): void
    {
        $type = $this->classifier->classifyException(new \LogicException('we should not be here'));
        $this->assertSame(FailureClassifier::INTERNAL, $type);
    }
}
