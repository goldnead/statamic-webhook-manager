<?php

namespace Goldnead\WebhookManager\Contracts;

use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;

interface InboundActionHandlerInterface
{
    public function handle(): string;

    public function label(): string;

    /**
     * Execute the inbound action with the mapped payload.
     *
     * @return array{ok:bool, message:string, data:array}
     *
     * TODO: REVIEW — narrow the return shape once the inbound action layer is fully designed.
     */
    public function handleAction(InboundEndpoint $endpoint, array $mappedPayload, array $rawPayload): array;
}
