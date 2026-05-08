<?php

namespace Goldnead\WebhookManager\Actions;

use Goldnead\WebhookManager\Contracts\ActionInterface;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Actions\DispatchOutboundWebhookAction;
use Goldnead\WebhookManager\Repositories\OutboundWebhookRepository;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\ExecutionResult;

/**
 * Rule action that triggers an existing outbound webhook by handle.
 *
 * Rule config: { "webhook_handle": "notify-crm" }
 *
 * Re-uses `DispatchOutboundWebhookAction` so the snapshot / queue / sync
 * branching is identical to a direct trigger-driven dispatch.
 */
class SendOutboundWebhookAction implements ActionInterface
{
    public function __construct(
        protected OutboundWebhookRepository $hooks,
        protected DispatchOutboundWebhookAction $dispatch,
    ) {
    }

    public function handle(): string
    {
        return 'send_outbound_webhook';
    }

    public function label(): string
    {
        return 'Send outbound webhook';
    }

    public function execute(array $config, ExecutionContext $context): ExecutionResult
    {
        $handle = (string) ($config['webhook_handle'] ?? '');
        if ($handle === '') {
            return ExecutionResult::fail('Missing required config.webhook_handle.');
        }

        $hook = $this->hooks->findByHandle($handle);
        if (! $hook) {
            return ExecutionResult::fail("Outbound webhook '{$handle}' not found.");
        }
        if (! $hook->enabled) {
            return ExecutionResult::fail("Outbound webhook '{$handle}' is disabled.");
        }

        try {
            $deliveryId = ($this->dispatch)($hook, $context);
            return ExecutionResult::ok('Outbound webhook dispatched.', [
                'webhook_handle' => $handle,
                'delivery_id' => $deliveryId,
            ]);
        } catch (\Throwable $e) {
            return ExecutionResult::fail("Failed to dispatch '{$handle}': ".$e->getMessage());
        }
    }
}
