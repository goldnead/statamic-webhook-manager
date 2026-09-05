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
        return __('webhook-manager::messages.cp.preset_discord');
    }

    public function fields(): array
    {
        return [
            $this->urlFieldDef(__('webhook-manager::messages.cp.preset_fields.url_discord_label'), __('webhook-manager::messages.cp.preset_fields.url_discord_hint')),
            $this->messageField(),
        ];
    }

    protected function payloadTemplate(array $input): string
    {
        $message = (string) ($input['message'] ?? '') ?: $this->defaultMessage();

        return $this->jsonMessage('content', $message);
    }
}
