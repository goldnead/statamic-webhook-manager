<?php

namespace Goldnead\WebhookManager\Listeners;

use Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface;
use Goldnead\WebhookManager\Events\DeliveryFailedTerminally;
use Goldnead\WebhookManager\Notifications\DeliveryFailedNotification;
use Goldnead\WebhookManager\Services\Logging\SystemLogger;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/**
 * Alerts an admin (email + optional Slack/Discord/Teams webhook) when a
 * delivery fails for good. Throttled per webhook so a broken endpoint can't
 * flood the recipients.
 */
class SendFailureAlertListener
{
    public function __construct(
        protected Cache $cache,
        protected SystemLogger $logger,
        protected OutboundWebhookRepositoryInterface $webhooks,
    ) {
    }

    public function handle(DeliveryFailedTerminally $event): void
    {
        if (! config('webhook-manager.alerts.enabled', true)) {
            return;
        }

        $delivery = $event->delivery;
        $hook = $this->webhooks->find($delivery->outbound_webhook_id);
        $hookId = $hook?->id ?? 'unknown';

        // Throttle: at most one alert per webhook per throttle window.
        $throttle = (int) config('webhook-manager.alerts.throttle_minutes', 15);
        $key = "webhook-manager:alert:{$hookId}";
        if ($throttle > 0 && $this->cache->has($key)) {
            return;
        }
        if ($throttle > 0) {
            $this->cache->put($key, true, now()->addMinutes($throttle));
        }

        $this->sendMail($delivery);
        $this->sendSlack($delivery, $hook?->name ?? 'Webhook');
    }

    protected function sendMail($delivery): void
    {
        if (! config('webhook-manager.alerts.mail.enabled', true)) {
            return;
        }

        $recipients = (array) config('webhook-manager.alerts.mail.recipients', []);
        if (empty($recipients)) {
            return;
        }

        try {
            Notification::route('mail', $recipients)
                ->notify(new DeliveryFailedNotification($delivery));
        } catch (\Throwable $e) {
            $this->logger->error('alert_mail_failed', $e->getMessage(), ['delivery_id' => $delivery->id]);
        }
    }

    protected function sendSlack($delivery, string $name): void
    {
        $url = (string) config('webhook-manager.alerts.slack.webhook_url', '');
        if ($url === '') {
            return;
        }

        $text = sprintf(
            "🚨 Webhook delivery failed: *%s*\nURL: %s\nStatus: %s\nError: %s",
            $name,
            (string) $delivery->request_url,
            $delivery->response_status ?: '—',
            (string) ($delivery->error_message ?? $delivery->error_type),
        );

        try {
            Http::timeout(10)->post($url, ['text' => $text]);
        } catch (\Throwable $e) {
            $this->logger->error('alert_slack_failed', $e->getMessage(), ['delivery_id' => $delivery->id]);
        }
    }
}
