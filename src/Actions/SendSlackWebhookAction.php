<?php

namespace Goldnead\WebhookManager\Actions;

use Goldnead\WebhookManager\Contracts\ActionInterface;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\ExecutionResult;
use Illuminate\Support\Facades\Http;

/**
 * Post a message to a Slack-compatible incoming webhook URL. Works with
 * Slack, Discord (with `?wait=true`), Mattermost and similar.
 *
 * Rule config:
 *   - `url` (string, required) — incoming webhook URL
 *   - `text` (string, required) — already-rendered message body
 *   - `username` (string, optional)
 *   - `channel` (string, optional, Slack-specific)
 *   - `extra` (array, optional) — merged into the JSON body for advanced fields
 */
class SendSlackWebhookAction implements ActionInterface
{
    public function handle(): string
    {
        return 'send_slack_webhook';
    }

    public function label(): string
    {
        return 'Send Slack/Discord webhook';
    }

    public function execute(array $config, ExecutionContext $context): ExecutionResult
    {
        $url = (string) ($config['url'] ?? '');
        $text = (string) ($config['text'] ?? '');

        if ($url === '' || $text === '') {
            return ExecutionResult::fail('config.url and config.text are required.');
        }

        try {
            $body = ['text' => $text];
            if (! empty($config['username'])) {
                $body['username'] = (string) $config['username'];
            }
            if (! empty($config['channel'])) {
                $body['channel'] = (string) $config['channel'];
            }
            if (is_array($config['extra'] ?? null)) {
                $body = array_merge($body, $config['extra']);
            }

            $response = Http::timeout(10)->post($url, $body);

            if (! $response->successful()) {
                return ExecutionResult::fail("Slack webhook responded with HTTP {$response->status()}.", [
                    'status' => $response->status(),
                ]);
            }

            return ExecutionResult::ok('Slack webhook posted.', [
                'status' => $response->status(),
            ]);
        } catch (\Throwable $e) {
            return ExecutionResult::fail('Failed to post Slack webhook: '.$e->getMessage());
        }
    }
}
