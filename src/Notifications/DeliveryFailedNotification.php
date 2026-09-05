<?php

namespace Goldnead\WebhookManager\Notifications;

use Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeliveryFailedNotification extends Notification
{
    public function __construct(public Delivery $delivery) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $hook = app(OutboundWebhookRepositoryInterface::class)
            ->find($this->delivery->outbound_webhook_id);
        $name = $hook?->name ?? 'Webhook';

        return (new MailMessage)
            ->error()
            ->subject(__('webhook-manager::messages.cp.notify_subject', ['name' => $name]))
            ->line(__('webhook-manager::messages.cp.notify_intro'))
            ->line(__('webhook-manager::messages.cp.notify_webhook', ['name' => $name]))
            ->line(__('webhook-manager::messages.cp.notify_url', ['url' => (string) $this->delivery->request_url]))
            ->line(__('webhook-manager::messages.cp.notify_status', ['status' => $this->delivery->response_status ?: '—']))
            ->line(__('webhook-manager::messages.cp.notify_error', ['error' => (string) ($this->delivery->error_message ?? $this->delivery->error_type)]))
            ->line(__('webhook-manager::messages.cp.notify_attempts', ['attempts' => (int) $this->delivery->attempts]));
    }
}
