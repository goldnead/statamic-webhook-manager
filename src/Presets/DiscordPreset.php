<?php

namespace Goldnead\WebhookManager\Presets;

class DiscordPreset extends AbstractPreset
{
    public function handle(): string
    {
        return 'discord';
    }

    public function label(): string
    {
        return 'Discord';
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
        return 'Post a message to a Discord channel via a channel webhook.';
    }

    public function fields(): array
    {
        return [
            $this->urlFieldDef('Discord Webhook URL', 'Channel Settings → Integrations → Webhooks → Copy URL.'),
            $this->messageField(),
        ];
    }

    protected function payloadTemplate(array $input): string
    {
        $message = (string) ($input['message'] ?? '') ?: $this->defaultMessage();

        return $this->jsonMessage('content', $message);
    }
}
