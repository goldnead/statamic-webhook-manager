<?php

namespace Goldnead\WebhookManager\Services\Inbound;

use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Illuminate\Http\JsonResponse;

class InboundResponseBuilder
{
    public function build(InboundEndpoint $endpoint, bool $success, array $data = []): JsonResponse
    {
        $config = $endpoint->response_config ?? [];
        $status = (int) ($success
            ? ($config['success_status'] ?? 200)
            : ($config['failure_status'] ?? 422));

        return response()->json([
            'ok' => $success,
            'data' => $data,
        ], $status);
    }
}
