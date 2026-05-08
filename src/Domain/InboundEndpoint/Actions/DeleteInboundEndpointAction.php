<?php

namespace Goldnead\WebhookManager\Domain\InboundEndpoint\Actions;

use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;

class DeleteInboundEndpointAction
{
    public function __invoke(InboundEndpoint $endpoint): void
    {
        $endpoint->delete();
    }
}
