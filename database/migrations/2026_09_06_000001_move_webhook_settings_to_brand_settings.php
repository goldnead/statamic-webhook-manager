<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move the Control-Panel setting overrides from this addon's own
 * `webhook_settings` table into the suite's shared `brand_settings`.
 *
 * The settings screen is no longer this package's. `statamic-brand-context`
 * owns the table, the form and the config override for every addon in the
 * suite, and it stores each row under a namespace and a brand. This migration
 * carries the existing rows across so an install that had customised its retry
 * or logging behaviour keeps that behaviour after the upgrade instead of
 * silently reverting to the packaged defaults.
 *
 * **The old table is not dropped.** It stays for one minor version. A rollback
 * to the previous release re-registers the old screen, which reads
 * `webhook_settings` — and if this migration had dropped it, that rollback
 * would come back up with every setting reset to the packaged default and no
 * indication that anything was lost. Dropping it is a separate migration in a
 * later release, once there is nothing left to roll back to.
 *
 * **Insert-only, never overwrite.** A key that already has a row on the target
 * brand is left alone. Running `migrate` twice must not undo a value the
 * operator changed on the new screen in between, and on a second pass every
 * key is already there — which is what makes this idempotent without relying on
 * the unique index to swallow a duplicate.
 *
 * **Everything goes to the default brand.** The old table had no brand column
 * at all — deliberately, see the migration that created it — so there is
 * exactly one set of values and no information anywhere about which brand they
 * were meant for. The default brand is the only answer that does not invent
 * one. On a multi-brand install the other brands start with no overrides, which
 * is the same state they were in before: they were reading these values through
 * a table that could not tell them apart.
 *
 * **No secret ever crosses.** `brand_settings` is in every database backup and
 * every export, so a key whose name says it carries a credential is left in the
 * old table rather than copied. The settings definition never offered one — the
 * alert webhook URL and the signing secrets are `.env` and the secret store,
 * and were excluded from the form on purpose — so on a normal install this
 * filter matches nothing. It is here for the row somebody wrote by hand.
 */
return new class extends Migration
{
    /** The namespace every migrated row carries. Must match Settings::settingsNamespace(). */
    private const NAMESPACE = 'webhook-manager';

    /**
     * Key fragments that mark a value as a credential. Same list the Debug
     * screen masks its config printout by, and generous for the same reason: a
     * false positive leaves one row behind in a table that is not being
     * deleted, a false negative puts a credential into every backup.
     */
    private const SECRET_NEEDLES = [
        'secret', 'token', 'password', 'passwd', 'api_key', 'apikey',
        'webhook_url', 'private_key', 'credential',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('webhook_settings')) {
            return;
        }

        $rows = DB::table('webhook_settings')->get(['key', 'value']);

        if ($rows->isEmpty()) {
            return;
        }

        if (! Schema::hasTable('brand_settings')) {
            throw new RuntimeException(
                'Cannot move the Webhook Manager settings: the `brand_settings` table does not exist. '
                .'It is created by goldnead/statamic-brand-context; upgrade that package and run '
                .'`php artisan migrate` again. Nothing was changed, and `webhook_settings` still holds '
                .'every value.'
            );
        }

        $brandId = $this->defaultBrandId();

        $existing = DB::table('brand_settings')
            ->where('brand_id', $brandId)
            ->where('namespace', self::NAMESPACE)
            ->pluck('key')
            ->all();

        $existing = array_flip($existing);
        $now = now();
        $insert = [];

        foreach ($rows as $row) {
            $key = (string) $row->key;

            if (isset($existing[$key]) || $this->isSecretKey($key)) {
                continue;
            }

            $insert[] = [
                'brand_id' => $brandId,
                'namespace' => self::NAMESPACE,
                'key' => $key,
                // The raw column value, not a decoded-and-re-encoded one. Both
                // columns are JSON and both are read through the same `json`
                // cast, so copying the string is the only step that cannot
                // change an integer into a string on the way.
                'value' => $row->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($insert !== []) {
            DB::table('brand_settings')->insert($insert);
        }
    }

    /**
     * Take the migrated rows back out.
     *
     * Scoped to the keys that are still sitting in `webhook_settings` — which
     * is exactly the set this migration could have written, because the old
     * table is never emptied. A row on the shared screen for some other key is
     * not this migration's and is left alone.
     *
     * A value changed on the new screen after the upgrade *is* removed here,
     * and that is the correct trade: rolling back re-activates the old screen,
     * which reads the old table, and leaving a shared-layer row behind would
     * mean two screens disagreeing about the same setting.
     */
    public function down(): void
    {
        if (! Schema::hasTable('brand_settings') || ! Schema::hasTable('webhook_settings')) {
            return;
        }

        $keys = DB::table('webhook_settings')->pluck('key')->all();

        if ($keys === []) {
            return;
        }

        DB::table('brand_settings')
            ->where('namespace', self::NAMESPACE)
            ->whereIn('key', $keys)
            ->delete();
    }

    /**
     * The brand the existing rows belong to.
     *
     * Handle first, because that is what `brand-context.default_handle` names
     * and what an operator would recognise; then the `is_default` flag, for an
     * install whose handle was renamed; then the lowest id, for one where
     * brand-context's `insertOrIgnore` found the handle taken and created
     * nothing. If none of the three answers, this throws rather than guessing —
     * a settings row on the wrong brand is a configuration one tenant did not
     * ask for and cannot see.
     */
    private function defaultBrandId(): int
    {
        if (! Schema::hasTable('brands')) {
            throw new RuntimeException(
                'Cannot move the Webhook Manager settings: the `brands` table does not exist. '
                .'Webhook Manager requires goldnead/statamic-brand-context; install it and run its '
                .'migrations first, then run `php artisan migrate` again. Nothing was changed.'
            );
        }

        $handle = (string) config('brand-context.default_handle', 'default');

        $id = DB::table('brands')->where('handle', $handle)->min('id')
            ?? DB::table('brands')->where('is_default', true)->min('id')
            ?? DB::table('brands')->min('id');

        if ($id === null) {
            throw new RuntimeException(
                'Cannot move the Webhook Manager settings: the `brands` table holds no rows, so there '
                ."is no brand to assign the stored overrides to. Create a brand (handle `{$handle}`, "
                .'`is_default = true`) and run `php artisan migrate` again. Nothing was changed, '
                .'and no brand id was invented.'
            );
        }

        return (int) $id;
    }

    private function isSecretKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::SECRET_NEEDLES as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
};
