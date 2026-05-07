<?php

namespace Goldnead\WebhookManager\Repositories;

use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Illuminate\Support\Collection;

/**
 * TODO: REVIEW — kept thin until the inbound module ships in a later iteration.
 */
class InboundEndpointRepository
{
    public function findByHandle(string $handle): ?InboundEndpoint
    {
        return InboundEndpoint::where('handle', $handle)->first();
    }

    public function all(): Collection
    {
        return InboundEndpoint::orderBy('name')->get();
    }

    public function countActive(): int
    {
        return InboundEndpoint::where('enabled', true)->count();
    }
}
