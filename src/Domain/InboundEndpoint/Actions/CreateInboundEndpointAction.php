<?php

namespace Goldnead\WebhookManager\Domain\InboundEndpoint\Actions;

use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Illuminate\Support\Str;

class CreateInboundEndpointAction
{
    public function __invoke(array $attributes): InboundEndpoint
    {
        $attributes = $this->normalize($attributes);

        $endpoint = new InboundEndpoint();
        $endpoint->fill($attributes);
        $endpoint->save();

        return $endpoint->fresh();
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
        $attributes['replay_protection_enabled'] = (bool) ($attributes['replay_protection_enabled'] ?? false);
        $attributes['logging_mode'] = $attributes['logging_mode'] ?? 'partial';
        $attributes['action_type'] = $attributes['action_type'] ?? 'noop';

        return $attributes;
    }
}
