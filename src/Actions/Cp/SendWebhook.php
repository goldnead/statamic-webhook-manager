<?php

namespace Goldnead\WebhookManager\Actions\Cp;

use Goldnead\WebhookManager\Domain\OutboundWebhook\Actions\DispatchOutboundWebhookAction;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Support\StatamicSnapshot;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;
use Statamic\Actions\Action;
use Statamic\Contracts\Entries\Entry;

/**
 * Native CP entry action: manually fire a chosen outbound webhook for the
 * selected entries. Builds a synthetic trigger context from each entry and
 * runs it through the same delivery pipeline as automatic triggers.
 */
class SendWebhook extends Action
{
    public static function title()
    {
        return __('webhook-manager::messages.send_webhook');
    }

    public function visibleTo($item)
    {
        return $item instanceof Entry
            && OutboundWebhook::where('enabled', true)->exists();
    }

    public function authorize($user, $item)
    {
        return $user?->can('manage outbound webhooks');
    }

    public function buttonText()
    {
        /** @phpstan-ignore-next-line — count provided by the framework */
        return trans_choice('webhook-manager::messages.send_webhook_button', $this->items->count());
    }

    protected function fieldItems()
    {
        return [
            'webhook' => [
                'type' => 'select',
                'display' => __('Webhook'),
                'instructions' => __('Which outbound webhook to fire for the selected entries.'),
                'options' => OutboundWebhook::where('enabled', true)
                    ->orderBy('name')
                    ->pluck('name', 'uuid')
                    ->all(),
                'validate' => ['required'],
            ],
        ];
    }

    public function run($items, $values)
    {
        $hook = OutboundWebhook::where('uuid', $values['webhook'] ?? null)->first();

        if (! $hook) {
            throw new \RuntimeException(__('webhook-manager::messages.send_webhook_missing'));
        }

        $dispatch = app(DispatchOutboundWebhookAction::class);

        foreach ($items as $entry) {
            $payload = StatamicSnapshot::entry($entry);

            $event = new TriggerEvent(
                triggerHandle: $hook->trigger_type ?: 'entry.saved',
                sourceType: 'entry',
                sourceReference: (string) ($payload['id'] ?? ''),
                payload: $payload,
                site: $payload['site'] ?? null,
            );

            $dispatch($hook, new ExecutionContext($event));
        }

        return __('webhook-manager::messages.send_webhook_done', [
            'name' => $hook->name,
            'count' => $items->count(),
        ]);
    }
}
