<?php

namespace Goldnead\WebhookManager\Actions;

use Goldnead\WebhookManager\Contracts\ActionInterface;
use Goldnead\WebhookManager\Sending\BrandMailer;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\ExecutionResult;

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
        return __('webhook-manager::messages.cp.rule_actions.send_email');
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

            // Rules are brand-scoped (see Storage\BrandSegments); until now the
            // mail they sent was not. On a host serving several brands from one
            // process, Mail::raw() meant one brand's rule going out through
            // another brand's relay — rejected there, or worse, accepted under
            // the wrong identity.
            //
            // The brand comes from the ambient context, which is the same brand
            // whose directory this rule was read from. config.from still applies
            // where no brand declares a sender, which is every single-brand
            // install; where one does, the brand wins and the rule cannot
            // impersonate it.
            $sent = app(BrandMailer::class)->sendRaw(
                null,
                null,
                $body,
                function ($message) use ($recipients, $subject, $config): void {
                    foreach ($recipients as $recipient) {
                        $message->to($recipient);
                    }
                    $message->subject($subject);
                    if (! empty($config['from']) && is_string($config['from'])) {
                        $message->from($config['from']);
                    }
                }
            );

            if (! $sent) {
                // The refusal is already in the log with the reason. Failing the
                // action rather than reporting success is the point: a rule that
                // says "email sent" while nothing left the building is worse
                // than one that says it could not send.
                return ExecutionResult::fail('Email not sent: the brand refused the sender identity. See the log for the reason.');
            }

            return ExecutionResult::ok('Email sent.', [
                'to' => $recipients,
                'subject' => $subject,
            ]);
        } catch (\Throwable $e) {
            return ExecutionResult::fail('Failed to send email: '.$e->getMessage());
        }
    }
}
