<?php

namespace Goldnead\WebhookManager\Presets;

class MicrosoftTeamsPreset extends AbstractPreset
{
    public function handle(): string
    {
        return 'microsoft_teams';
    }

    public function label(): string
    {
        return 'Microsoft Teams';
    }

    public function icon(): string
    {
        return 'mail-chat-bubble-text';
    }

    public function category(): string
    {
        return 'Chat';
    }

    public function description(): string
    {
        return __('webhook-manager::messages.cp.preset_teams');
    }

    public function fields(): array
    {
        return [
            $this->urlFieldDef(__('webhook-manager::messages.cp.preset_fields.url_teams_label'), __('webhook-manager::messages.cp.preset_fields.url_teams_hint')),
            $this->messageField(),
        ];
    }

    protected function payloadTemplate(array $input): string
    {
        $message = (string) ($input['message'] ?? '') ?: $this->defaultMessage();

        return $this->jsonMessage('text', $message);
    }
}
