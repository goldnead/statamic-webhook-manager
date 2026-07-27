<?php

namespace Goldnead\WebhookManager\Services\Logging;

use Illuminate\Support\Facades\DB;

/**
 * Writes secret-related audit entries — created, rotated, removed.
 *
 * The audit trail answers "who changed the credentials of this webhook, and
 * when". It deliberately answers nothing else: the secret itself, and any
 * value from the auth config, is never written here. Only the *fact* of the
 * change, its timestamp, the actor and the auth scheme are recorded.
 *
 * TODO: REVIEW — currently uses raw inserts to avoid an audit model;
 * upgrade to a real model + repository when audits are surfaced in CP.
 */
class AuditLogger
{
    public const ACTION_CREATED = 'created';

    public const ACTION_ROTATED = 'rotated';

    public const ACTION_REMOVED = 'removed';

    public const TARGET_OUTBOUND = 'outbound';

    public const TARGET_INBOUND = 'inbound';

    public function record(
        string $action,
        string $targetType,
        int $targetId,
        ?string $actorId = null,
        array $context = [],
        ?int $brandId = null,
    ): void {
        DB::table('webhook_secret_audits')->insert([
            // The table is brand-scoped and `brand_id` is NOT NULL. The audit
            // row belongs to the brand of the webhook it describes, not to
            // whatever brand happens to be current when a queue worker runs.
            'brand_id' => $brandId ?? $this->currentBrandId(),
            'actor_id' => $actorId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'action' => $action,
            'context' => json_encode($this->sanitize($context)),
            'created_at' => now(),
        ]);
    }

    /**
     * Record a secret lifecycle change by diffing the auth config before and
     * after a save. Returns the action that was recorded, or null when the
     * credentials did not change — a plain re-save must not produce noise.
     *
     * @param  array<string,mixed>  $before
     * @param  array<string,mixed>  $after
     */
    public function recordSecretChange(
        string $targetType,
        int $targetId,
        array $before,
        array $after,
        ?string $authType = null,
        ?string $handle = null,
        ?int $brandId = null,
    ): ?string {
        $had = $before !== [];
        $has = $after !== [];

        $action = match (true) {
            ! $had && $has => self::ACTION_CREATED,
            $had && ! $has => self::ACTION_REMOVED,
            $had && $has && $before !== $after => self::ACTION_ROTATED,
            default => null,
        };

        if ($action === null) {
            return null;
        }

        $this->record($action, $targetType, $targetId, $this->actorId(), [
            'auth_type' => $authType,
            'handle' => $handle,
            // Key NAMES only — never values. Enough to tell a rotated HMAC
            // secret from a swapped bearer token when reading the trail.
            'config_keys' => array_values(array_map('strval', array_keys($after))),
        ], $brandId);

        return $action;
    }

    /** Brand of the row being audited; falls back to the active brand. */
    protected function currentBrandId(): int
    {
        try {
            return (int) app('brand-context')->currentId();
        } catch (\Throwable) {
            return 1;
        }
    }

    /**
     * Current CP user, if any. Scheduled commands and queue workers legitimately
     * have none — the entry is then recorded with a null actor rather than
     * being skipped, because "changed by nobody we can name" still beats
     * "not recorded at all".
     */
    protected function actorId(): ?string
    {
        try {
            $id = auth()->id();
        } catch (\Throwable) {
            return null;
        }

        return $id === null ? null : (string) $id;
    }

    /**
     * Last line of defence: strip anything that looks like a credential from
     * the context before it is persisted, whatever the caller passed in.
     *
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    protected function sanitize(array $context): array
    {
        $forbidden = ['secret', 'token', 'password', 'value', 'key', 'auth_config', 'authorization'];

        foreach (array_keys($context) as $key) {
            if (in_array(strtolower((string) $key), $forbidden, true)) {
                unset($context[$key]);
            }
        }

        return $context;
    }
}
