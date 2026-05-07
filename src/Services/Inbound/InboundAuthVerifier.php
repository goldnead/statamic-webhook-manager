<?php

namespace Goldnead\WebhookManager\Services\Inbound;

use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Goldnead\WebhookManager\Registries\AuthSchemeRegistry;
use Illuminate\Http\Request;

class InboundAuthVerifier
{
    public function __construct(protected AuthSchemeRegistry $schemes)
    {
    }

    public function verify(Request $request, InboundEndpoint $endpoint): bool
    {
        $scheme = $this->schemes->get($endpoint->auth_type ?? 'none');
        if (! $scheme) {
            return false;
        }
        return $scheme->verify($request, $endpoint->auth_config ?? []);
    }
}
