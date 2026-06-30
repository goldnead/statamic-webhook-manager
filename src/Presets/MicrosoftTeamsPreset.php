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
        return 'Post a message to a Microsoft Teams channel via an Incoming Webhook.';
    }

    public function fields(): array
    {
        return [
            $this->urlFieldDef('Teams Incoming Webhook URL', 'Channel → Connectors → Incoming Webhook → Create.'),
            $this->messageField(),
        ];
    }

    protected function payloadTemplate(array $input): string
    {
        $message = (string) ($input['message'] ?? '') ?: $this->defaultMessage();

        return $this->jsonMessage('text', $message);
    }
}
