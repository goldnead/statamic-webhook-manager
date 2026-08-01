<?php

namespace Goldnead\WebhookManager\Domain\OutboundWebhook\Actions;

use Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Services\Logging\AuditLogger;

class UpdateOutboundWebhookAction
{
    public function __construct(
        protected OutboundWebhookRepositoryInterface $repository,
        protected AuditLogger $audit,
    ) {}

    public function __invoke(OutboundWebhook $hook, array $attributes): OutboundWebhook
    {
        // Snapshot before anything is filled — this is what a rotation is
        // diffed against.
        $before = (array) ($hook->auth_config ?? []);

        // Auth config that comes through as an empty array means
        // "no change" — we don't want to wipe a stored secret because
        // the user merely re-saved an unchanged form.
        if (isset($attributes['auth_config']) && $attributes['auth_config'] === []) {
            unset($attributes['auth_config']);
        }

        // Switching a hook back to "no auth" must actually drop the stored
        // credentials. Leaving them encrypted in the row means a later switch
        // back to `hmac` silently resurrects a secret nobody remembers.
        if (($attributes['auth_type'] ?? null) === 'none') {
            $attributes['auth_config'] = [];
        }

        $hook->fill($attributes);

        $saved = $this->repository->save($hook);

        $this->audit->recordSecretChange(
            AuditLogger::TARGET_OUTBOUND,
            (int) $saved->id,
            $before,
            (array) ($saved->auth_config ?? []),
            $saved->auth_type,
            $saved->handle,
            (int) $saved->brand_id,
        );

        return $saved;
    }
}
