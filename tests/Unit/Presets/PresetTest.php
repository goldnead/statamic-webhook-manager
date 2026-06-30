<?php

namespace Goldnead\WebhookManager\Tests\Unit\Presets;

use Goldnead\WebhookManager\Presets\DiscordPreset;
use Goldnead\WebhookManager\Presets\GenericJsonPreset;
use Goldnead\WebhookManager\Presets\SlackPreset;
use Goldnead\WebhookManager\Presets\ZapierPreset;
use Goldnead\WebhookManager\Registries\PresetRegistry;
use Goldnead\WebhookManager\Tests\TestCase;

class PresetTest extends TestCase
{
    public function test_registry_registers_the_built_in_presets(): void
    {
        $registry = app(PresetRegistry::class);

        $this->assertNotNull($registry->get('slack'));
        $this->assertNotNull($registry->get('discord'));
        $this->assertNotNull($registry->get('zapier'));
        $this->assertNotNull($registry->get('generic_json'));
        $this->assertGreaterThanOrEqual(7, count($registry->all()));

        $gallery = $registry->gallery();
        $this->assertArrayHasKey('handle', $gallery[0]);
        $this->assertArrayHasKey('icon', $gallery[0]);
    }

    public function test_slack_preset_builds_a_text_payload(): void
    {
        $attrs = (new SlackPreset())->build([
            'name' => 'Notify team',
            'trigger_type' => 'entry.published',
            'webhook_url' => 'https://hooks.slack.com/services/T/B/x',
            'message' => 'New: {{ entry:title }}',
        ]);

        $this->assertSame('https://hooks.slack.com/services/T/B/x', $attrs['url']);
        $this->assertSame('POST', $attrs['method']);
        $this->assertSame('slack', $attrs['preset_handle']);
        $this->assertSame('{"text": "New: {{ entry:title }}"}', $attrs['payload_template']);
        $this->assertJson(str_replace(['{{ entry:title }}'], ['x'], $attrs['payload_template']));
    }

    public function test_discord_preset_builds_a_content_payload(): void
    {
        $attrs = (new DiscordPreset())->build([
            'name' => 'Discord',
            'trigger_type' => 'entry.published',
            'webhook_url' => 'https://discord.com/api/webhooks/1/x',
        ]);

        $this->assertStringStartsWith('{"content":', $attrs['payload_template']);
        $this->assertSame('discord', $attrs['preset_handle']);
    }

    public function test_zapier_preset_uses_the_structured_event_template(): void
    {
        $attrs = (new ZapierPreset())->build([
            'name' => 'Zap',
            'trigger_type' => 'entry.published',
            'webhook_url' => 'https://hooks.zapier.com/x',
        ]);

        $this->assertStringContainsString('"event"', $attrs['payload_template']);
        $this->assertStringContainsString('{{ entry:id }}', $attrs['payload_template']);
        $this->assertJson(preg_replace('/\{\{[^}]+\}\}/', 'x', $attrs['payload_template']));
    }

    public function test_generic_json_preset_reads_the_url_field_and_custom_template(): void
    {
        $attrs = (new GenericJsonPreset())->build([
            'name' => 'Custom',
            'trigger_type' => 'entry.published',
            'url' => 'https://example.com/hook',
            'payload_template' => '{"foo":"bar"}',
        ]);

        $this->assertSame('https://example.com/hook', $attrs['url']);
        $this->assertSame('{"foo":"bar"}', $attrs['payload_template']);
    }
}
