<?php

namespace Goldnead\WebhookManager\Notifications;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeliveryFailedNotification extends Notification
{
    public function __construct(public Delivery $delivery)
    {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $hook = app(\Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface::class)
            ->find($this->delivery->outbound_webhook_id);
        $name = $hook?->name ?? 'Webhook';

        return (new MailMessage())
            ->error()
            ->subject(__('Webhook delivery failed: :name', ['name' => $name]))
            ->line(__('A webhook delivery has failed after all retries.'))
            ->line(__('Webhook: :name', ['name' => $name]))
            ->line(__('URL: :url', ['url' => (string) $this->delivery->request_url]))
            ->line(__('Status: :status', ['status' => $this->delivery->response_status ?: '—']))
            ->line(__('Error: :error', ['error' => (string) ($this->delivery->error_message ?? $this->delivery->error_type)]))
            ->line(__('Attempts: :attempts', ['attempts' => (int) $this->delivery->attempts]));
    }
}
