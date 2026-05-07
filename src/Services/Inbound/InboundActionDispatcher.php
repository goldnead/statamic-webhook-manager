<?php

namespace Goldnead\WebhookManager\Services\Inbound;

use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;

/**
 * TODO: REVIEW — dispatches the configured inbound action.
 * v1 returns no-op; future iterations will call into:
 *  - create/update entry
 *  - create form submission
 *  - dispatch internal event
 *  - call outbound webhook
 *  - send slack/email
 */
class InboundActionDispatcher
{
    /**
     * @return array{ok:bool, message:string, data:array}
     */
    public function dispatch(InboundEndpoint $endpoint, array $mappedPayload, array $rawPayload): array
    {
        return [
            'ok' => false,
            'message' => 'Inbound action layer not yet implemented.',
            'data' => [],
        ];
    }
}
