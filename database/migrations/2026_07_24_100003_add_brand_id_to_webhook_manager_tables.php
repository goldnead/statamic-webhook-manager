<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P1 brand-scoping for Webhook Manager.
 *
 * Adds `brand_id` to every stateful table and hardens uniqueness so the same
 * logical identifier (handle) can exist independently per brand. Root tables
 * (outbounds, inbounds, rules, templates) carry brand_id directly; child tables
 * (deliveries, logs, secret_audits) carry it denormalised for query-time
 * defense — every read filters on brand_id instead of trusting a join.
 *
 * Backfill assigns all existing rows to the default brand ("Bestand = Default").
 * Child rows inherit brand_id from their parent where resolvable, else default.
 *
 * Note on idempotency: the outbound delivery `idempotency_key` is indexed, not
 * unique, and inbound replay/idempotency keys live in the cache
 * (ReplayProtectionService), keyed by endpoint id — never a global DB unique.
 * So there is no global-unique idempotency constraint to brand-scope; we only
 * add a composite (brand_id, idempotency_key) index for query-time isolation.
 *
 * ---------------------------------------------------------------------------
 * Every step below is state-driven, and the default brand is looked up rather
 * than assumed. Both of those were added after the fact, for two failures this
 * file used to be able to produce.
 *
 * **Nothing was guarded.** The published shape added `brand_id` to seven tables
 * unconditionally, then dropped four `handle` uniques, added a composite index
 * and froze the column NOT NULL — all of it unconditional. No engine rolls DDL
 * back and a migration that throws is not recorded as run, so an install that
 * aborted anywhere after the first `alter table` was left half-converted and
 * with the migration still pending. The retry then died on `duplicate column
 * name: brand_id`, an error about step 1 that says nothing whatsoever about the
 * step that actually failed, and sends whoever reads it looking in the wrong
 * place — while the four `handle` uniques may already be gone and not yet
 * replaced. So this migration now asks the schema what state it is in before
 * each step: `hasColumn` per table before adding the column, `getIndexes` before
 * reworking a unique or adding the composite index, and it drops
 * `{$table}_handle_unique` only where that index is still present. Running it
 * twice is a no-op.
 *
 * The backfills carry the same requirement, for a different reason. They used
 * to be unconditional `update()` calls with no `where`, which is not merely
 * wasteful on a second run: it is destructive. On a multi-brand install every
 * row of every table — outbounds that were deliberately moved to brand 3,
 * deliveries that inherited from them — would be rewritten to the default
 * brand, silently and in one statement. Every backfill is therefore restricted
 * to `whereNull('brand_id')`. A row that already carries a brand is never
 * touched; only rows the conversion has not reached yet are stamped.
 *
 * **The default brand was guessed.** The lookup read `is_default = true` and
 * fell back to the literal `1`. brand-context creates the default brand with
 * `insertOrIgnore`, so an install whose `brands.handle` was already taken ends
 * up with no `is_default` row at all, and nothing in that schema constrains
 * `is_default` to a single row or reserves id 1. On such an install the literal
 * fallback stamped every webhook, delivery and log with a brand id that may
 * belong to somebody else's brand — or to no brand at all, since there is no
 * foreign key here to refuse it — and three steps later the column was frozen
 * NOT NULL, which makes the wrong answer look exactly like the right one. A
 * webhook is a credential and a destination; pointing a brand's entire webhook
 * surface at the wrong tenant is not a cosmetic error, and it is silent.
 *
 * So the lookup now tries `is_default` first, then the lowest existing brand id,
 * and if neither answers — or if `brands` does not exist at all — it throws and
 * names what is missing. Stopping costs an operator one command
 * (`php artisan migrate` after installing goldnead/statamic-brand-context);
 * guessing costs data whose wrongness nobody can see from the outside. A
 * migration may refuse to run. It may not invent an owner.
 * ---------------------------------------------------------------------------
 */
return new class extends Migration
{
    /** Root tables: brand_id + handle-unique rework. */
    private array $rootTables = [
        'webhook_outbounds',
        'webhook_inbounds',
        'webhook_rules',
        'webhook_templates',
    ];

    /** Child/log tables: brand_id denormalised, inherited from the parent. */
    private array $childTables = [
        'webhook_deliveries',
        'webhook_logs',
        'webhook_secret_audits',
    ];

    public function up(): void
    {
        // Resolved before the first `alter table`, so an install with no brand
        // to assign its existing rows to stops while the schema is still whole.
        $defaultId = $this->canProbeSchema() ? $this->defaultBrandId() : null;

        // 1. Add nullable brand_id + index everywhere.
        //    Per table, and only where it is missing: after an interrupted run
        //    some tables have the column and some do not.
        foreach ($this->allTables() as $t) {
            if ($this->canProbeSchema() && Schema::hasColumn($t, 'brand_id')) {
                continue;
            }

            Schema::table($t, function (Blueprint $table) {
                $table->unsignedBigInteger('brand_id')->nullable()->after('id')->index();
            });
        }

        // 2./3. Backfill, root tables first so the children can inherit.
        if ($defaultId !== null) {
            $this->backfill($defaultId);
        }

        // 4. Enforce NOT NULL now that every row is backfilled.
        //
        //    Unconditional, unlike everything around it. Restating a column
        //    that is already NOT NULL is accepted by both engines and changes
        //    nothing, so there is no state worth probing for.
        foreach ($this->allTables() as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->unsignedBigInteger('brand_id')->nullable(false)->change();
            });
        }

        // 5. Unique-rework: handle is unique per brand, not globally.
        foreach ($this->rootTables as $t) {
            $this->scopeHandleUniqueToBrand($t);
        }

        // 6. Query-time isolation index for outbound delivery idempotency keys.
        if (! $this->canProbeSchema() || ! $this->hasIndexOver('webhook_deliveries', ['brand_id', 'idempotency_key'])) {
            Schema::table('webhook_deliveries', function (Blueprint $table) {
                $table->index(['brand_id', 'idempotency_key']);
            });
        }
    }

    /**
     * Stamp every row that has no brand yet, and only those.
     *
     * The `whereNull` is the whole point. Without it a second run — or a run on
     * an install that was already multi-brand — rewrites every outbound,
     * delivery and log to the default brand in one statement, with no way to
     * tell afterwards which rows had been placed deliberately.
     */
    private function backfill(int $defaultId): void
    {
        // Root tables → default brand.
        foreach ($this->rootTables as $t) {
            DB::table($t)->whereNull('brand_id')->update(['brand_id' => $defaultId]);
        }

        // Child tables — inherit from parent, else default.
        // Deliveries inherit from their outbound webhook.
        DB::table('webhook_deliveries')->whereNull('brand_id')->update([
            'brand_id' => DB::raw(
                '(SELECT o.brand_id FROM webhook_outbounds o WHERE o.id = webhook_deliveries.outbound_webhook_id)'
            ),
        ]);

        // Logs inherit from the related outbound webhook, else the related inbound endpoint.
        DB::table('webhook_logs')->whereNull('brand_id')->update([
            'brand_id' => DB::raw(
                'COALESCE('.
                '(SELECT o.brand_id FROM webhook_outbounds o WHERE o.id = webhook_logs.related_webhook_id),'.
                '(SELECT i.brand_id FROM webhook_inbounds i WHERE i.id = webhook_logs.related_endpoint_id)'.
                ')'
            ),
        ]);

        // Secret audits inherit from their polymorphic target.
        DB::table('webhook_secret_audits')->whereNull('brand_id')->update([
            'brand_id' => DB::raw(
                'CASE target_type '.
                "WHEN 'outbound' THEN (SELECT o.brand_id FROM webhook_outbounds o WHERE o.id = webhook_secret_audits.target_id) ".
                "WHEN 'inbound' THEN (SELECT i.brand_id FROM webhook_inbounds i WHERE i.id = webhook_secret_audits.target_id) ".
                'ELSE NULL END'
            ),
        ]);

        // Any child row whose parent was missing → default brand (never leave null).
        foreach ($this->childTables as $t) {
            DB::table($t)->whereNull('brand_id')->update(['brand_id' => $defaultId]);
        }
    }

    /**
     * Take brand scoping back out — or refuse, before touching anything.
     *
     * The whole point of `up()` is that two brands may hold the same handle, so
     * by the time anybody rolls back, the data may no longer satisfy the global
     * unique this has to restore. The engine says so with `Duplicate entry 'x'
     * for key 'webhook_outbounds_handle_unique'` from inside an `alter table`,
     * at which point the brand-scoped unique on that table is already dropped,
     * the migration is not recorded as rolled back, and the install carries no
     * uniqueness on `handle` at all — a table where anything can now be written
     * twice, produced by a command that looked like it was undoing something.
     *
     * Deduplicating for the operator is worse than stopping. An outbound webhook
     * row is a destination URL plus the credential that signs it; two brands
     * that both call theirs `crm-lead` point at two different systems, and there
     * is nothing in the row that says which one the install should keep. Picking
     * the lower id would silently repoint one tenant's traffic at the other's
     * endpoint, which is not a rollback, it is a data-loss event with a green
     * console.
     *
     * So the collisions are collected first, across all four root tables, and if
     * there are any this throws with the table and the handles named — while
     * every table is still whole and the migration is still recorded as run.
     * Resolving it is one rename per collision, then the same command again.
     */
    public function down(): void
    {
        $this->refuseWhileHandlesCollide();

        if ($this->hasIndexOver('webhook_deliveries', ['brand_id', 'idempotency_key'])) {
            Schema::table('webhook_deliveries', function (Blueprint $table) {
                $table->dropIndex(['brand_id', 'idempotency_key']);
            });
        }

        foreach ($this->rootTables as $t) {
            $indexes = $this->indexesOn($t);

            if ($indexes->has($t.'_brand_id_handle_unique')) {
                Schema::table($t, function (Blueprint $table) use ($t) {
                    $table->dropUnique($t.'_brand_id_handle_unique');
                });
            }

            if (! $indexes->has($t.'_handle_unique')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->unique('handle');
                });
            }
        }

        foreach ($this->allTables() as $t) {
            if (! Schema::hasColumn($t, 'brand_id')) {
                continue;
            }

            Schema::table($t, function (Blueprint $table) use ($t) {
                $table->dropIndex($t.'_brand_id_index');
                $table->dropColumn('brand_id');
            });
        }
    }

    /**
     * Stop the rollback while any root table holds a handle more than once.
     *
     * Runs before the first statement of `down()`, so a refusal costs nothing:
     * the schema is untouched and the migration stays recorded as run.
     */
    private function refuseWhileHandlesCollide(): void
    {
        if (! $this->canProbeSchema()) {
            return;
        }

        $collisions = [];

        foreach ($this->rootTables as $t) {
            if (! Schema::hasTable($t) || ! Schema::hasColumn($t, 'handle')) {
                continue;
            }

            $handles = DB::table($t)
                ->select('handle')
                ->groupBy('handle')
                ->havingRaw('count(*) > 1')
                ->orderBy('handle')
                ->pluck('handle')
                ->all();

            if ($handles !== []) {
                $collisions[$t] = $handles;
            }
        }

        if ($collisions === []) {
            return;
        }

        $detail = collect($collisions)
            ->map(fn (array $handles, string $table) => '  - '.$table.': '.implode(', ', $handles))
            ->implode("\n");

        throw new RuntimeException(
            "Cannot roll the Webhook Manager brand scoping back: `handle` would have to be globally unique\n"
            ."again, and these are held by more than one brand:\n\n"
            .$detail."\n\n"
            .'Rolling back means going back to one handle per install, and this migration will not choose '
            .'which of the rows survives — an outbound webhook carries a target URL and the credential that '
            ."signs it, so keeping the wrong one silently repoints a brand's traffic. Rename or remove one "
            ."side of every collision above, then run `php artisan migrate:rollback` again.\n"
            .'Nothing was changed; brand scoping is still fully in place.'
        );
    }

    /**
     * @return list<string>
     */
    private function allTables(): array
    {
        return [...$this->rootTables, ...$this->childTables];
    }

    /**
     * The brand every pre-brand row belongs to, or a stop.
     *
     * `is_default` first, then the lowest id that exists, and nothing else. The
     * literal `1` this used to fall back to is not a default brand, it is a
     * guess about somebody else's database — see the note at the top of the
     * file.
     */
    private function defaultBrandId(): int
    {
        if (! Schema::hasTable('brands')) {
            throw new RuntimeException(
                'Cannot brand-scope the Webhook Manager tables: the `brands` table does not exist. '
                .'Webhook Manager requires goldnead/statamic-brand-context; install it and run its '
                .'migrations first, then run `php artisan migrate` again. Nothing was changed.'
            );
        }

        $id = DB::table('brands')->where('is_default', true)->min('id')
            ?? DB::table('brands')->min('id');

        if ($id === null) {
            throw new RuntimeException(
                'Cannot brand-scope the Webhook Manager tables: the `brands` table holds no rows, so '
                .'there is no brand to assign the existing webhooks, deliveries and logs to. '
                .'goldnead/statamic-brand-context creates the default brand with insertOrIgnore, which '
                .'does nothing when the handle is already taken — check `brand-context.default_handle` '
                .'and create a brand (`is_default = true`), then run `php artisan migrate` again. '
                .'Nothing was changed, and no brand id was invented.'
            );
        }

        return (int) $id;
    }

    /**
     * Move a table's global `handle` unique onto (brand_id, handle).
     *
     * Reads the indexes that are actually there rather than assuming the
     * published starting state: the global unique is dropped only where it
     * still exists, and the brand-scoped one is built only where it does not.
     * An install that already carries the scoped unique — a completed run, or
     * an interrupted one that got this far — comes out of here untouched.
     */
    private function scopeHandleUniqueToBrand(string $t): void
    {
        $probe = $this->canProbeSchema();

        if (! $probe || $this->indexesOn($t)->has($t.'_handle_unique')) {
            Schema::table($t, function (Blueprint $table) use ($t) {
                $table->dropUnique($t.'_handle_unique');
            });
        }

        if ($probe && $this->hasIndexOver($t, ['brand_id', 'handle'], unique: true)) {
            return;
        }

        Schema::table($t, function (Blueprint $table) {
            $table->unique(['brand_id', 'handle']);
        });
    }

    /**
     * Whether the connection can be asked what state it is in.
     *
     * Under `migrate --pretend` — and under IndexKeyLengthTest, which compiles
     * these files through the MySQL grammar without a server — nothing is
     * executed and every read answers empty. Every guard above would then
     * conclude "not there yet": no `drop index`, no state to skip, and a
     * compiled DDL that no longer describes what this migration does to a real
     * install. So when the connection cannot answer, the guards fall back to
     * the published pre-migration schema — no brand_id, global handle uniques
     * in place, nothing brand-scoped — which is the state this file was written
     * for and the one worth compiling. The backfill is skipped outright: it
     * reads a brand id, and a pretend run has no rows to read and none to write.
     */
    private function canProbeSchema(): bool
    {
        return ! DB::connection()->pretending();
    }

    /**
     * Whether the table already carries an index over exactly these columns,
     * whatever it happens to be called. Names are an implementation detail of
     * whichever release built the index; the columns are the guarantee.
     *
     * @param  list<string>  $columns
     */
    private function hasIndexOver(string $table, array $columns, bool $unique = false): bool
    {
        return $this->indexesOn($table)->contains(
            fn (array $index) => $index['columns'] === $columns && (! $unique || ($index['unique'] ?? false))
        );
    }

    /**
     * @return Collection<string, array>
     */
    private function indexesOn(string $table): Collection
    {
        return collect(Schema::getIndexes($table))->keyBy('name');
    }
};
