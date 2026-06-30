<?php

namespace Goldnead\WebhookManager\Domain\InboundEndpoint\Actions;

use Goldnead\WebhookManager\Contracts\Repositories\InboundEndpointRepositoryInterface;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;

class DeleteInboundEndpointAction
{
    public function __construct(protected InboundEndpointRepositoryInterface $repository)
    {
    }

    public function __invoke(InboundEndpoint $endpoint): void
    {
        $this->repository->delete($endpoint);
    }
}
