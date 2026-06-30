<?php

namespace Goldnead\WebhookManager\Presets;

class N8nPreset extends AbstractPreset
{
    public function handle(): string
    {
        return 'n8n';
    }

    public function label(): string
    {
        return 'n8n';
    }

    public function icon(): string
    {
        return 'flash-bolt-lightning';
    }

    public function category(): string
    {
        return 'Automation';
    }

    public function description(): string
    {
        return 'Send a structured JSON event to an n8n Webhook node.';
    }

    public function fields(): array
    {
        return [
            $this->urlFieldDef('n8n Webhook URL', 'Add a Webhook node and copy its Production URL.'),
        ];
    }

    protected function payloadTemplate(array $input): string
    {
        return $this->structuredEventTemplate();
    }
}
