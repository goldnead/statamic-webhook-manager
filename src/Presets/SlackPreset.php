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
        return 'Post a message to a Slack channel via an Incoming Webhook.';
    }

    public function fields(): array
    {
        return [
            $this->urlFieldDef('Slack Incoming Webhook URL', 'Create one at api.slack.com → Incoming Webhooks.'),
            $this->messageField(),
        ];
    }

    protected function payloadTemplate(array $input): string
    {
        $message = (string) ($input['message'] ?? '') ?: $this->defaultMessage();

        return $this->jsonMessage('text', $message);
    }
}
