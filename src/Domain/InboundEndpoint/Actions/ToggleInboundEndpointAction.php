<?php

namespace Goldnead\WebhookManager\Domain\InboundEndpoint\Actions;

use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;

class ToggleInboundEndpointAction
{
    public function __invoke(InboundEndpoint $endpoint, ?bool $enabled = null): InboundEndpoint
    {
        $endpoint->enabled = $enabled ?? ! $endpoint->enabled;
        $endpoint->save();
        return $endpoint->fresh();
    }
}
