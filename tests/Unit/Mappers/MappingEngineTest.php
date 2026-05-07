<?php

namespace Goldnead\WebhookManager\Tests\Unit\Mappers;

use Goldnead\WebhookManager\Mappers\DefaultValueResolver;
use Goldnead\WebhookManager\Mappers\MappingEngine;
use Goldnead\WebhookManager\Mappers\PathResolver;
use Goldnead\WebhookManager\Mappers\TypeCoercer;
use Goldnead\WebhookManager\Mappers\ValueTransformer;
use PHPUnit\Framework\TestCase;

class MappingEngineTest extends TestCase
{
    protected function engine(): MappingEngine
    {
        return new MappingEngine(
            new PathResolver(),
            new ValueTransformer(),
            new TypeCoercer(),
            new DefaultValueResolver(),
        );
    }

    public function test_it_maps_nested_paths_via_dot_notation(): void
    {
        $config = [
            'email' => ['path' => 'contact.email'],
            'name' => ['path' => 'contact.name'],
            'origin' => ['path' => 'source'],
        ];
        $payload = [
            'contact' => ['email' => 'a@example.com', 'name' => 'Anna'],
            'source' => 'hubspot',
        ];

        $result = $this->engine()->map($config, $payload);

        $this->assertTrue($result['ok']);
        $this->assertSame([
            'email' => 'a@example.com',
            'name' => 'Anna',
            'origin' => 'hubspot',
        ], $result['data']);
        $this->assertSame([], $result['errors']);
    }

    public function test_it_resolves_array_index_paths(): void
    {
        $config = [
            'first_email' => ['path' => 'contacts[0].email'],
            'second_email' => ['path' => 'contacts[1].email'],
        ];
        $payload = [
            'contacts' => [
                ['email' => 'a@example.com'],
                ['email' => 'b@example.com'],
            ],
        ];

        $result = $this->engine()->map($config, $payload);

        $this->assertTrue($result['ok']);
        $this->assertSame('a@example.com', $result['data']['first_email']);
        $this->assertSame('b@example.com', $result['data']['second_email']);
    }

    public function test_it_falls_back_to_defaults_for_missing_paths(): void
    {
        $config = [
            'origin' => ['path' => 'source', 'default' => 'unknown'],
            'received_at' => ['default' => '@now'],
        ];

        $result = $this->engine()->map($config, []);

        $this->assertTrue($result['ok']);
        $this->assertSame('unknown', $result['data']['origin']);
        $this->assertNotEmpty($result['data']['received_at']);
        // ATOM format like 2026-05-07T12:00:00+00:00
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $result['data']['received_at'],
        );
    }

    public function test_it_returns_error_for_missing_required_field(): void
    {
        $config = [
            'email' => ['path' => 'contact.email', 'required' => true],
        ];

        $result = $this->engine()->map($config, ['contact' => []]);

        $this->assertFalse($result['ok']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString("'email'", $result['errors'][0]);
    }

    public function test_it_applies_value_transforms(): void
    {
        $config = [
            'email' => ['path' => 'email', 'transform' => 'lowercase'],
            'name' => ['path' => 'name', 'transform' => 'trim'],
            'tags' => [
                'path' => 'tag_string',
                'transform' => ['name' => 'explode', 'separator' => ','],
            ],
        ];
        $payload = [
            'email' => 'A@EXAMPLE.COM',
            'name' => '  Anna  ',
            'tag_string' => 'red, blue, green',
        ];

        $result = $this->engine()->map($config, $payload);

        $this->assertTrue($result['ok']);
        $this->assertSame('a@example.com', $result['data']['email']);
        $this->assertSame('Anna', $result['data']['name']);
        $this->assertSame(['red', 'blue', 'green'], $result['data']['tags']);
    }

    public function test_it_coerces_types(): void
    {
        $config = [
            'count' => ['path' => 'count', 'type' => 'int'],
            'active' => ['path' => 'active', 'type' => 'bool'],
            'price' => ['path' => 'price', 'type' => 'float'],
        ];
        $payload = ['count' => '42', 'active' => 'yes', 'price' => '19.99'];

        $result = $this->engine()->map($config, $payload);

        $this->assertTrue($result['ok']);
        $this->assertSame(42, $result['data']['count']);
        $this->assertTrue($result['data']['active']);
        $this->assertSame(19.99, $result['data']['price']);
    }

    public function test_it_uses_target_key_as_path_when_no_path_supplied(): void
    {
        $config = [
            'email' => ['required' => true],
            'source' => [],
        ];
        $payload = ['email' => 'a@example.com', 'source' => 'hubspot'];

        $result = $this->engine()->map($config, $payload);

        $this->assertTrue($result['ok']);
        $this->assertSame('a@example.com', $result['data']['email']);
        $this->assertSame('hubspot', $result['data']['source']);
    }
}
