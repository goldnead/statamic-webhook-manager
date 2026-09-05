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
        return __('webhook-manager::messages.cp.preset_n8n');
    }

    public function fields(): array
    {
        return [
            $this->urlFieldDef(__('webhook-manager::messages.cp.preset_fields.url_n8n_label'), __('webhook-manager::messages.cp.preset_fields.url_n8n_hint')),
        ];
    }

    protected function payloadTemplate(array $input): string
    {
        return $this->structuredEventTemplate();
    }
}
