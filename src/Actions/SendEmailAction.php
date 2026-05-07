<?php

namespace Goldnead\WebhookManager\Actions;

use Goldnead\WebhookManager\Contracts\ActionInterface;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\ExecutionResult;
use Illuminate\Support\Facades\Mail;

/**
 * Send a plain-text email notification.
 *
 * Rule config:
 *   - `to` (string|array, required)
 *   - `subject` (string, required)
 *   - `body` (string, required) — already-rendered text; rendering is the
 *     caller's responsibility (use the template renderer in your rule
 *     pipeline if you need variable interpolation).
 *   - `from` (string, optional) — falls back to mail.from.address
 *
 * TODO: REVIEW — once the template module ships, accept a template handle
 * to render with the trigger context instead of pre-rendered text.
 */
class SendEmailAction implements ActionInterface
{
    public function handle(): string
    {
        return 'send_email';
    }

    public function label(): string
    {
        return 'Send email notification';
    }

    public function execute(array $config, ExecutionContext $context): ExecutionResult
    {
        $to = $config['to'] ?? null;
        $subject = (string) ($config['subject'] ?? '');
        $body = (string) ($config['body'] ?? '');

        if (empty($to) || $subject === '' || $body === '') {
            return ExecutionResult::fail('config.to, config.subject and config.body are required.');
        }

        try {
            $recipients = is_array($to) ? $to : [$to];
            Mail::raw($body, function ($message) use ($recipients, $subject, $config) {
                foreach ($recipients as $recipient) {
                    $message->to($recipient);
                }
                $message->subject($subject);
                if (! empty($config['from']) && is_string($config['from'])) {
                    $message->from($config['from']);
                }
            });

            return ExecutionResult::ok('Email sent.', [
                'to' => $recipients,
                'subject' => $subject,
            ]);
        } catch (\Throwable $e) {
            return ExecutionResult::fail('Failed to send email: '.$e->getMessage());
        }
    }
}
