<?php

namespace Goldnead\WebhookManager\Presets;

class SlackPreset extends AbstractPreset
{
    public function handle(): string
    {
        return 'slack';
    }

    public function label(): string
    {
        return 'Slack';
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
        return __('webhook-manager::messages.cp.preset_slack');
    }

    public function fields(): array
    {
        return [
            $this->urlFieldDef(__('webhook-manager::messages.cp.preset_fields.url_slack_label'), __('webhook-manager::messages.cp.preset_fields.url_slack_hint')),
            $this->messageField(),
        ];
    }

    protected function payloadTemplate(array $input): string
    {
        $message = (string) ($input['message'] ?? '') ?: $this->defaultMessage();

        return $this->jsonMessage('text', $message);
    }
}
