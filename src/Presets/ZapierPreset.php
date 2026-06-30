<?php

namespace Goldnead\WebhookManager\Presets;

class ZapierPreset extends AbstractPreset
{
    public function handle(): string
    {
        return 'zapier';
    }

    public function label(): string
    {
        return 'Zapier';
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
        return 'Send a structured JSON event to a Zapier "Catch Hook" trigger.';
    }

    public function fields(): array
    {
        return [
            $this->urlFieldDef('Zapier Catch Hook URL', 'Zap → Trigger → Webhooks by Zapier → Catch Hook → Copy URL.'),
        ];
    }

    protected function payloadTemplate(array $input): string
    {
        return $this->structuredEventTemplate();
    }
}
