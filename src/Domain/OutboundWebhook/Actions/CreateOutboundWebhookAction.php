<?php

namespace Goldnead\WebhookManager\Domain\OutboundWebhook\Actions;

use Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Services\Logging\AuditLogger;
use Illuminate\Support\Str;

class CreateOutboundWebhookAction
{
    public function __construct(
        protected OutboundWebhookRepositoryInterface $repository,
        protected AuditLogger $audit,
    ) {
    }

    public function __invoke(array $attributes): OutboundWebhook
    {
        $hook = $this->repository->create($this->normalize($attributes));

        // A webhook that is born with credentials is the first entry in its
        // own secret trail. Audited here rather than in the controller so
        // presets and programmatic creation are covered too.
        $this->audit->recordSecretChange(
            AuditLogger::TARGET_OUTBOUND,
            (int) $hook->id,
            [],
            (array) ($hook->auth_config ?? []),
            $hook->auth_type,
            $hook->handle,
            (int) $hook->brand_id,
        );

        return $hook;
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
