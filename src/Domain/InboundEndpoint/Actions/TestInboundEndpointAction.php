<?php

namespace Goldnead\WebhookManager\Domain\InboundEndpoint\Actions;

use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Goldnead\WebhookManager\Services\Inbound\InboundActionDispatcher;
use Goldnead\WebhookManager\Services\Inbound\InboundMappingService;

/**
 * Test the inbound pipeline against a sample payload — bypasses the
 * HTTP auth layer (it's already authorized via CP permissions) and
 * skips replay protection so the same sample can be re-run repeatedly.
 *
 * Returns the same shape as a real inbound dispatch result so the CP
 * UI can render the same panel for both. Mapping errors are surfaced
 * as `ok: false` with the errors array in `data`.
 */
class TestInboundEndpointAction
{
    public function __construct(
        protected InboundMappingService $mapping,
        protected InboundActionDispatcher $dispatcher,
    ) {
    }

    /**
     * @return array{ok:bool, message:string, mapped:array, data:array, errors?:array<int,string>}
     */
    public function __invoke(InboundEndpoint $endpoint, array $samplePayload): array
    {
        $mapped = $this->mapping->map($endpoint->mapping_config ?? null, $samplePayload);

        if (! $mapped['ok']) {
            return [
                'ok' => false,
                'message' => 'Mapping failed.',
                'mapped' => [],
                'data' => [],
                'errors' => $mapped['errors'] ?? [],
            ];
        }

        $result = $this->dispatcher->dispatch($endpoint, $mapped['data'], $samplePayload);

        return [
            'ok' => (bool) ($result['ok'] ?? false),
            'message' => (string) ($result['message'] ?? ''),
            'mapped' => $mapped['data'],
            'data' => (array) ($result['data'] ?? []),
        ];
    }
}
