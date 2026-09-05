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
        return __('webhook-manager::messages.cp.preset_zapier');
    }

    public function fields(): array
    {
        return [
            $this->urlFieldDef(__('webhook-manager::messages.cp.preset_fields.url_zapier_label'), __('webhook-manager::messages.cp.preset_fields.url_zapier_hint')),
        ];
    }

    protected function payloadTemplate(array $input): string
    {
        return $this->structuredEventTemplate();
    }
}
