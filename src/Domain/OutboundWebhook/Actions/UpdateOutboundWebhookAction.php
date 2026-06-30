<?php

namespace Goldnead\WebhookManager\Domain\OutboundWebhook\Actions;

use Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;

class UpdateOutboundWebhookAction
{
    public function __construct(protected OutboundWebhookRepositoryInterface $repository)
    {
    }

    public function __invoke(OutboundWebhook $hook, array $attributes): OutboundWebhook
    {
        // Auth config that comes through as an empty array means
        // "no change" — we don't want to wipe a stored secret because
        // the user merely re-saved an unchanged form.
        if (isset($attributes['auth_config']) && $attributes['auth_config'] === []) {
            unset($attributes['auth_config']);
        }

        $hook->fill($attributes);

        return $this->repository->save($hook);
    }
}
