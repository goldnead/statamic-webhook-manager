<?php

namespace Goldnead\WebhookManager\Tests\Unit\Templates;

use Goldnead\WebhookManager\Registries\VariableResolverRegistry;
use Goldnead\WebhookManager\Templates\Exceptions\TemplateRenderException;
use Goldnead\WebhookManager\Templates\TemplateRenderer;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;
use PHPUnit\Framework\TestCase;

class TemplateRendererTest extends TestCase
{
    private function rendererWithDefaults(): TemplateRenderer
    {
        $registry = new VariableResolverRegistry();
        $registry->registerDefaults();
        return new TemplateRenderer($registry);
    }

    private function entryContext(array $payload): ExecutionContext
    {
        $event = new TriggerEvent(
            triggerHandle: 'entry.published',
            sourceType: 'entry',
            sourceReference: $payload['id'] ?? null,
            payload: $payload,
            site: $payload['site'] ?? 'default',
        );
        return new ExecutionContext($event);
    }

    public function test_renders_simple_entry_token(): void
    {
        $renderer = $this->rendererWithDefaults();
        $context = $this->entryContext(['id' => '1', 'title' => 'Hello']);

        $output = $renderer->render('Title is {{ entry:title }}', $context);
        $this->assertSame('Title is Hello', $output);
    }

    public function test_renders_nested_entry_data_token(): void
    {
        $renderer = $this->rendererWithDefaults();
        $context = $this->entryContext([
            'id' => '1',
            'title' => 'Hello',
            'data' => ['summary' => 'Short summary'],
        ]);

        $output = $renderer->render('{{ entry:data.summary }}', $context);
        $this->assertSame('Short summary', $output);
    }

    public function test_renders_system_timestamp(): void
    {
        $renderer = $this->rendererWithDefaults();
        $context = $this->entryContext(['id' => '1']);

        $output = $renderer->render('{{ system:date }}', $context);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $output);
    }

    public function test_uses_default_when_token_resolves_to_null(): void
    {
        $renderer = $this->rendererWithDefaults();
        $context = $this->entryContext(['id' => '1']);

        $output = $renderer->render("{{ entry:title|default('untitled') }}", $context);
        $this->assertSame('untitled', $output);
    }

    public function test_throws_on_unknown_namespace_without_default(): void
    {
        $renderer = $this->rendererWithDefaults();
        $context = $this->entryContext(['id' => '1']);

        $this->expectException(TemplateRenderException::class);
        $renderer->render('{{ unknown:foo }}', $context);
    }

    public function test_validate_reports_unknown_namespace(): void
    {
        $renderer = $this->rendererWithDefaults();
        $issues = $renderer->validate('{{ unknown:foo }}');
        $this->assertNotEmpty($issues);
    }
}
