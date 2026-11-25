<?php

namespace Goldnead\WebhookManager\Listeners;

use Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface;
use Goldnead\WebhookManager\Events\DeliveryFailedTerminally;
use Goldnead\WebhookManager\Notifications\DeliveryFailedNotification;
use Goldnead\WebhookManager\Services\Logging\SystemLogger;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
    ) {}

    public function handle(DeliveryFailedTerminally $event): void
    {
        if (! config('webhook-manager.alerts.enabled', true)) {
            return;
        }

        $delivery = $event->delivery;
        $hook = $this->webhooks->find($delivery->outbound_webhook_id);
        $hookId = $hook?->id ?? 'unknown';

        // Throttle: at most one alert per webhook per throttle window. The
        // window only starts once an alert actually went out: a failed send
        // must not swallow the only alarm of an outage for the next minutes.
        $throttle = (int) config('webhook-manager.alerts.throttle_minutes', 15);
        $key = "webhook-manager:alert:{$hookId}";
        if ($throttle > 0 && $this->cache->has($key)) {
            return;
        }

        // Short claim while sending: the window opens only after the send, so
        // parallel workers failing the same hook would otherwise all pass the
        // check above and each send the alert.
        $claim = $key.':sending';
        if ($throttle > 0 && ! $this->cache->add($claim, true, now()->addMinute())) {
            return;
        }

        try {
            $mail = $this->sendMail($delivery);
            $slack = $this->sendSlack($delivery, $hook?->name ?? 'Webhook');

            if ($mail === null && $slack === null) {
                // Nobody is configured to be told. Say so once per hour instead
                // of failing silently on every terminal failure.
                if ($this->cache->add('webhook-manager:alert:no-channel', true, now()->addHour())) {
                    Log::warning('[webhook-manager] no alert channel configured: terminal delivery failures raise no alert. Set webhook-manager.alerts.mail.recipients or alerts.slack.webhook_url.', [
                        'delivery_id' => $delivery->id,
                    ]);
                }

                return;
            }

            if ($throttle > 0 && ($mail === true || $slack === true)) {
                $this->cache->put($key, true, now()->addMinutes($throttle));
            }
        } finally {
            if ($throttle > 0) {
                $this->cache->forget($claim);
            }
        }
    }

    /**
     * @return bool|null true = sent, false = tried and failed, null = channel not configured
     */
    protected function sendMail($delivery): ?bool
    {
        if (! config('webhook-manager.alerts.mail.enabled', true)) {
            return null;
        }

        $recipients = (array) config('webhook-manager.alerts.mail.recipients', []);
        if (empty($recipients)) {
            return null;
        }

        try {
            Notification::route('mail', $recipients)
                ->notify(new DeliveryFailedNotification($delivery));

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('alert_mail_failed', $e->getMessage(), ['delivery_id' => $delivery->id]);

            return false;
        }
    }

    /**
     * @return bool|null true = sent, false = tried and failed, null = channel not configured
     */
    protected function sendSlack($delivery, string $name): ?bool
    {
        $url = (string) config('webhook-manager.alerts.slack.webhook_url', '');
        if ($url === '') {
            return null;
        }

        $text = sprintf(
            "🚨 Webhook delivery failed: *%s*\nURL: %s\nStatus: %s\nError: %s",
            $name,
            (string) $delivery->request_url,
            $delivery->response_status ?: '—',
            (string) ($delivery->error_message ?? $delivery->error_type),
        );

        try {
            // throw(): a 4xx/5xx answer is a failed alert, not a sent one.
            Http::timeout(10)->post($url, ['text' => $text])->throw();

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('alert_slack_failed', $e->getMessage(), ['delivery_id' => $delivery->id]);

            return false;
        }
    }
}
