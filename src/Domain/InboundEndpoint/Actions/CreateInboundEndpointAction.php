<?php

namespace Goldnead\WebhookManager\Domain\InboundEndpoint\Actions;

use Goldnead\WebhookManager\Contracts\Repositories\InboundEndpointRepositoryInterface;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Goldnead\WebhookManager\Services\Logging\AuditLogger;
use Illuminate\Support\Str;

class CreateInboundEndpointAction
{
    public function __construct(
        protected InboundEndpointRepositoryInterface $repository,
        protected AuditLogger $audit,
    ) {}

    public function __invoke(array $attributes): InboundEndpoint
    {
        $endpoint = $this->repository->create($this->normalize($attributes));

        $this->audit->recordSecretChange(
            AuditLogger::TARGET_INBOUND,
            (int) $endpoint->id,
            [],
            (array) ($endpoint->auth_config ?? []),
            $endpoint->auth_type,
            $endpoint->handle,
            (int) $endpoint->brand_id,
        );

        return $endpoint;
    }

    protected function normalize(array $attributes): array
    {
        $attributes['handle'] = $attributes['handle']
            ?? Str::slug($attributes['name'] ?? Str::random(8));
        $attributes['path'] = $attributes['path'] ?? $attributes['handle'];
        $attributes['enabled'] = (bool) ($attributes['enabled'] ?? true);
        $attributes['allowed_methods'] = $attributes['allowed_methods'] ?? ['POST'];
        $attributes['auth_type'] = $attributes['auth_type'] ?? 'static_header';
        $attributes['expected_content_type'] = $attributes['expected_content_type'] ?? 'application/json';
        $attributes['max_payload_kb'] = (int) ($attributes['max_payload_kb'] ?? 512);
        // On by default since 2.1.0. A public URL that a sender may retry — and
        // every serious sender retries on a timeout it cannot distinguish from
        // a failure — needs the duplicate to be recognised, or the action runs
        // twice. Existing endpoints keep whatever they were saved with; this
        // only decides what a new one starts as, and the CP still lets it be
        // switched off for a genuinely idempotent action.
        $attributes['replay_protection_enabled'] = (bool) ($attributes['replay_protection_enabled'] ?? true);
        $attributes['logging_mode'] = $attributes['logging_mode'] ?? 'partial';
        $attributes['action_type'] = $attributes['action_type'] ?? 'noop';

        return $attributes;
    }
}
