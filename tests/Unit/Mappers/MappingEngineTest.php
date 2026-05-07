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
    private function engine(): MappingEngine
    {
        return new MappingEngine(
            new PathResolver(),
            new ValueTransformer(),
            new TypeCoercer(),
            new DefaultValueResolver(),
        );
    }

    public function test_maps_dot_notation_paths_with_defaults_and_transforms(): void
    {
        $payload = [
            'contact' => ['email' => '  Anna@Example.com ', 'name' => 'Anna'],
            'source' => 'hubspot',
        ];

        $mapping = [
            'email' => ['path' => 'contact.email', 'transform' => 'trim', 'type' => 'string'],
            'title' => ['path' => 'contact.name', 'required' => true],
            'origin' => ['path' => 'source'],
            'priority' => ['path' => 'priority', 'default' => 'normal'],
        ];

        $result = $this->engine()->map($mapping, $payload);

        $this->assertTrue($result['ok']);
        $this->assertSame('Anna@Example.com', $result['data']['email']);
        $this->assertSame('Anna', $result['data']['title']);
        $this->assertSame('hubspot', $result['data']['origin']);
        $this->assertSame('normal', $result['data']['priority']);
    }

    public function test_returns_errors_when_required_field_missing(): void
    {
        $result = $this->engine()->map([
            'email' => ['path' => 'contact.email', 'required' => true],
        ], ['contact' => []]);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['errors']);
    }
}
