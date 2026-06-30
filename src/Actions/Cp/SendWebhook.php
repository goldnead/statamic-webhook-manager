<?php

namespace Goldnead\WebhookManager\Actions\Cp;

use Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Actions\DispatchOutboundWebhookAction;
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
            && app(OutboundWebhookRepositoryInterface::class)->countActive() > 0;
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
                'options' => app(OutboundWebhookRepositoryInterface::class)->all()
                    ->filter(fn ($hook) => (bool) $hook->enabled)
                    ->mapWithKeys(fn ($hook) => [$hook->uuid => $hook->name])
                    ->all(),
                'validate' => ['required'],
            ],
        ];
    }

    public function run($items, $values)
    {
        $hook = app(OutboundWebhookRepositoryInterface::class)->findByUuid($values['webhook'] ?? '');

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
