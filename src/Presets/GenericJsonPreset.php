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
        return 'Generic JSON endpoint';
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
        return 'POST a JSON payload of your choice to any HTTP endpoint.';
    }

    protected function urlField(): string
    {
        return 'url';
    }

    public function fields(): array
    {
        return [
            $this->urlFieldDef('Destination URL', 'The endpoint that will receive the JSON POST.'),
            [
                'handle' => 'payload_template',
                'label' => 'Payload template',
                'type' => 'code',
                'instructions' => 'JSON body. Tokens like {{ entry:title }} are rendered per delivery.',
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
