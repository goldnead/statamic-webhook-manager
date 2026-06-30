<?php

namespace Goldnead\WebhookManager\Presets;

class MakePreset extends AbstractPreset
{
    public function handle(): string
    {
        return 'make';
    }

    public function label(): string
    {
        return 'Make (Integromat)';
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
        return 'Send a structured JSON event to a Make custom-webhook trigger.';
    }

    public function fields(): array
    {
        return [
            $this->urlFieldDef('Make Webhook URL', 'Scenario → Webhooks → Custom webhook → Copy address.'),
        ];
    }

    protected function payloadTemplate(array $input): string
    {
        return $this->structuredEventTemplate();
    }
}
