<?php

namespace Goldnead\WebhookManager\Domain\InboundEndpoint\Actions;

use Goldnead\WebhookManager\Contracts\Repositories\InboundEndpointRepositoryInterface;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;

class UpdateInboundEndpointAction
{
    public function __construct(protected InboundEndpointRepositoryInterface $repository)
    {
    }

    public function __invoke(InboundEndpoint $endpoint, array $attributes): InboundEndpoint
    {
        // Auth config that comes through as an empty array means
        // "no change" — we don't want to wipe a stored secret because
        // the user merely re-saved an unchanged form.
        if (isset($attributes['auth_config']) && $attributes['auth_config'] === []) {
            unset($attributes['auth_config']);
        }

        $endpoint->fill($attributes);

        return $this->repository->save($endpoint);
    }
}
