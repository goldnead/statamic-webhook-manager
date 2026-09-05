<?php

namespace Goldnead\WebhookManager\Presets;

class GenericJsonPreset extends AbstractPreset
{
    public function handle(): string
    {
        return 'generic_json';
    }

    public function label(): string
    {
        return __('webhook-manager::messages.cp.preset_generic_label');
    }

    public function icon(): string
    {
        return 'code-block';
    }

    public function category(): string
    {
        return 'Custom';
    }

    public function description(): string
    {
        return __('webhook-manager::messages.cp.preset_generic');
    }

    protected function urlField(): string
    {
        return 'url';
    }

    public function fields(): array
    {
        return [
            $this->urlFieldDef(__('webhook-manager::messages.cp.preset_fields.url_generic_label'), __('webhook-manager::messages.cp.preset_fields.url_generic_hint')),
            [
                'handle' => 'payload_template',
                'label' => __('webhook-manager::messages.cp.preset_fields.payload_template_label'),
                'type' => 'code',
                'instructions' => __('webhook-manager::messages.cp.preset_fields.payload_template_hint'),
                'required' => false,
                'default' => $this->structuredEventTemplate(),
            ],
        ];
    }

    protected function payloadTemplate(array $input): string
    {
        $template = trim((string) ($input['payload_template'] ?? ''));

        return $template !== '' ? $template : $this->structuredEventTemplate();
    }
}
