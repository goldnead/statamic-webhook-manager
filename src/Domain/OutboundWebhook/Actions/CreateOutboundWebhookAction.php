<?php

namespace Goldnead\WebhookManager\Domain\OutboundWebhook\Actions;

use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Illuminate\Support\Str;

class CreateOutboundWebhookAction
{
    public function __invoke(array $attributes): OutboundWebhook
    {
        $attributes = $this->normalize($attributes);

        $hook = new OutboundWebhook();
        $hook->fill($attributes);
        $hook->save();

        return $hook->fresh();
    }

    protected function normalize(array $attributes): array
    {
        $attributes['handle'] = $attributes['handle'] ?? Str::slug($attributes['name'] ?? Str::random(8));
        $attributes['enabled'] = (bool) ($attributes['enabled'] ?? true);
        $attributes['method'] = strtoupper($attributes['method'] ?? 'POST');
        $attributes['queue_enabled'] = (bool) ($attributes['queue_enabled'] ?? true);
        $attributes['follow_redirects'] = (bool) ($attributes['follow_redirects'] ?? true);
        $attributes['timeout_seconds'] = (int) ($attributes['timeout_seconds'] ?? 15);
        $attributes['log_body_mode'] = $attributes['log_body_mode'] ?? 'partial';
        $attributes['payload_type'] = $attributes['payload_type'] ?? 'raw_json';
        $attributes['auth_type'] = $attributes['auth_type'] ?? 'none';
        return $attributes;
    }
}
