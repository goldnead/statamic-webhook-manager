<?php

namespace Goldnead\WebhookManager\Presets;

use Goldnead\WebhookManager\Contracts\PresetInterface;
use Illuminate\Support\Str;

abstract class AbstractPreset implements PresetInterface
{
    public function icon(): string
    {
        return 'arrow-up-right';
    }

    public function category(): string
    {
        return 'Integration';
    }

    /** Preset-specific payload body (already valid JSON, tokens allowed). */
    abstract protected function payloadTemplate(array $input): string;

    /**
     * The URL field handle this preset collects (default "webhook_url"), so
     * build() can read it regardless of the label shown in the CP.
     */
    protected function urlField(): string
    {
        return 'webhook_url';
    }

    public function build(array $input): array
    {
        $name = trim((string) ($input['name'] ?? '')) ?: $this->label();

        return [
            'name' => $name,
            'handle' => $input['handle'] ?? Str::slug($name),
            'description' => $input['description'] ?? null,
            'enabled' => true,
            'trigger_type' => (string) ($input['trigger_type'] ?? 'entry.published'),
            'url' => (string) ($input[$this->urlField()] ?? ''),
            'method' => 'POST',
            'auth_type' => 'none',
            'payload_type' => 'raw_json',
            'payload_template' => $this->payloadTemplate($input),
            'queue_enabled' => true,
            'follow_redirects' => true,
            'timeout_seconds' => 15,
            'log_body_mode' => 'partial',
            'preset_handle' => $this->handle(),
        ];
    }

    /**
     * A structured JSON event body used by the automation presets
     * (Zapier/Make/n8n) — safe tokens that resolve for entry triggers and
     * degrade to empty strings otherwise.
     */
    protected function structuredEventTemplate(): string
    {
        return <<<'JSON'
{
    "event": "{{ system:trigger }}",
    "id": "{{ entry:id }}",
    "title": "{{ entry:title }}",
    "site": "{{ site:handle }}",
    "timestamp": "{{ system:timestamp_iso }}"
}
JSON;
    }

    /** Wrap a message string into a single-key JSON body, escaping safely. */
    protected function jsonMessage(string $key, string $message): string
    {
        return '{'.json_encode($key).': '.json_encode($message, JSON_UNESCAPED_SLASHES).'}';
    }

    /** The default chat message used when the user leaves the field blank. */
    protected function defaultMessage(): string
    {
        return 'New {{ entry:title }} ({{ system:trigger }})';
    }

    /** Shared "message" field for chat presets. */
    protected function messageField(): array
    {
        return [
            'handle' => 'message',
            'label' => 'Message',
            'type' => 'textarea',
            'instructions' => 'Supports tokens like {{ entry:title }} and {{ system:trigger }}.',
            'required' => false,
            'default' => $this->defaultMessage(),
        ];
    }

    /** Shared URL field for incoming-webhook style presets. */
    protected function urlFieldDef(string $label, string $instructions): array
    {
        return [
            'handle' => $this->urlField(),
            'label' => $label,
            'type' => 'text',
            'instructions' => $instructions,
            'required' => true,
        ];
    }
}
