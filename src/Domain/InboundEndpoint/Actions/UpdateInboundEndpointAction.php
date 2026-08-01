<?php

namespace Goldnead\WebhookManager\Domain\InboundEndpoint\Actions;

use Goldnead\WebhookManager\Contracts\Repositories\InboundEndpointRepositoryInterface;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Goldnead\WebhookManager\Services\Logging\AuditLogger;

class UpdateInboundEndpointAction
{
    public function __construct(
        protected InboundEndpointRepositoryInterface $repository,
        protected AuditLogger $audit,
    ) {}

    public function __invoke(InboundEndpoint $endpoint, array $attributes): InboundEndpoint
    {
        $before = (array) ($endpoint->auth_config ?? []);

        // Auth config that comes through as an empty array means
        // "no change" — we don't want to wipe a stored secret because
        // the user merely re-saved an unchanged form.
        if (isset($attributes['auth_config']) && $attributes['auth_config'] === []) {
            unset($attributes['auth_config']);
        }

        if (($attributes['auth_type'] ?? null) === 'none') {
            $attributes['auth_config'] = [];
        }

        $endpoint->fill($attributes);

        $saved = $this->repository->save($endpoint);

        $this->audit->recordSecretChange(
            AuditLogger::TARGET_INBOUND,
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
