<?php

namespace Goldnead\WebhookManager\Services\Logging;

use Illuminate\Support\Facades\DB;

/**
 * Writes secret-related audit entries — created, rotated, replaced.
 *
 * TODO: REVIEW — currently uses raw inserts to avoid an audit model;
 * upgrade to a real model + repository when audits are surfaced in CP.
 */
class AuditLogger
{
    public function record(string $action, string $targetType, int $targetId, ?string $actorId = null, array $context = []): void
    {
        DB::table('webhook_secret_audits')->insert([
            'actor_id' => $actorId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'action' => $action,
            'context' => json_encode($context),
            'created_at' => now(),
        ]);
    }
}
