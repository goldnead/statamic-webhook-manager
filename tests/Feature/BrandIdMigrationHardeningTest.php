<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Tests\Fixtures\WebhookManagerDataFixture;
use Goldnead\WebhookManager\Tests\MigrationPathTestCase;
use Illuminate\Database\Schema\Blueprint;

/**
 * The two ways the brand-scoping migration could damage a populated install.
 *
 * Both are invisible on the database every other test meets. testbench builds
 * an empty schema and migrates it to head in one uninterrupted run, with the
 * default brand sitting at id 1 because brand-context has just created it —
 * which is precisely the one state in which an unguarded `alter table`, a
 * backfill with no `where` and a hardcoded fallback of `1` all behave
 * correctly. So each case here starts from a real earlier release
 * (tests/Fixtures/released-migrations/v1.2.0, the last one before brand
 * scoping), puts real rows in, and only then bends the install into the shape
 * that exposes the defect.
 *
 * **Nothing was guarded.** The published migration added `brand_id` to seven
 * tables, dropped four uniques, added an index and froze a column, all of it
 * unconditional. No engine rolls DDL back and an aborted migration is not
 * recorded as run, so any interruption left a half-converted schema that the
 * retry could not get past: it died on `duplicate column name: brand_id`, an
 * error about the first statement that says nothing about the state the
 * install is actually in. And the backfill's missing `where` meant a re-run —
 * or a run on an install that had already placed rows in other brands —
 * rewrote every row of every table back to the default brand in one statement.
 *
 * **The default brand was guessed.** `?? 1`. brand-context inserts its default
 * brand with `insertOrIgnore` and nothing in that schema constrains
 * `is_default` to one row or reserves id 1, so an install can genuinely have no
 * default row — and stamping its webhooks, deliveries and logs with a brand id
 * that belongs to somebody else is not something the schema can catch
 * afterwards, because there is no foreign key here and the column is NOT NULL
 * three steps later. The migration has to stop and say so.
 */
class BrandIdMigrationHardeningTest extends MigrationPathTestCase
{
    private const MIGRATION = '2026_07_24_100003_add_brand_id_to_webhook_manager_tables';

    /**
     * The state an interrupted run leaves behind: some tables converted, some
     * not, and the migration still pending.
     */
    public function test_it_finishes_a_run_that_was_interrupted_halfway_through_adding_the_column(): void
    {
        $this->populatedPreBrandInstall();

        // Step 1 of the published migration got through three of the seven
        // tables before the process died. Built by hand rather than by watching
        // the old file fail, because on SQLite the migrator wraps a migration
        // in a transaction and would tidy the evidence away — on a production
        // MySQL server nothing does.
        foreach (['webhook_outbounds', 'webhook_inbounds', 'webhook_rules'] as $table) {
            $this->isolatedSchema()->table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('brand_id')->nullable()->after('id')->index();
            });
        }

        $before = $this->rowCounts();

        // This is the line that used to die with `duplicate column name:
        // brand_id` — an error about step 1, on an install whose real problem
        // is that steps 2 to 6 never happened.
        $this->migratePath($this->brandMigration());

        $this->assertContains(
            self::MIGRATION,
            $this->ranMigrations(),
            'the migration did not record itself as run on a half-converted install'
        );

        $this->assertSame($before, $this->rowCounts(), 'the recovery lost rows');

        foreach (WebhookManagerDataFixture::tables() as $table) {
            $this->assertSame(
                0,
                $this->isolated()->table($table)->whereNull('brand_id')->count(),
                "{$table} still holds rows with no brand after the recovery"
            );
        }

        // And the conversion the interrupted run never reached is really there:
        // the handle unique is brand-scoped, proven by writing both rows.
        $probe = WebhookManagerDataFixture::handleProbe(0);

        $this->assertFalse($this->duplicateOutboundIsAccepted($probe, $this->defaultBrandId()));
        $this->assertTrue($this->duplicateOutboundIsAccepted($probe, $this->secondBrandId()));
    }

    /**
     * A second run must change nothing — least of all the brand ids an operator
     * has already moved rows to.
     */
    public function test_running_it_twice_changes_nothing_and_keeps_existing_brand_ids(): void
    {
        $this->populatedPreBrandInstall();

        $this->migratePath($this->brandMigration());

        // The install becomes multi-brand, which is the entire point of the
        // migration: one webhook and its deliveries are moved to a second brand
        // on purpose.
        $second = $this->secondBrandId();
        $movedId = (int) $this->isolated()->table('webhook_outbounds')
            ->where('handle', 'slack-alert')
            ->value('id');

        $this->isolated()->table('webhook_outbounds')->where('id', $movedId)->update(['brand_id' => $second]);
        $this->isolated()->table('webhook_deliveries')->where('outbound_webhook_id', $movedId)->update(['brand_id' => $second]);
        $this->isolated()->table('webhook_logs')->where('related_webhook_id', $movedId)->update(['brand_id' => $second]);

        $before = $this->brandAssignments();
        $counts = $this->rowCounts();

        $this->assertContains($second, $before['webhook_outbounds'], 'the fixture install never became multi-brand');

        // An operator runs `php artisan migrate` again — after a restore, after
        // a deploy that replayed the ledger, or because the first run's output
        // was lost. The migration has to be a no-op.
        $this->isolated()->table('migrations')->where('migration', self::MIGRATION)->delete();

        $this->migratePath($this->brandMigration());

        $this->assertSame(
            $before,
            $this->brandAssignments(),
            'the second run rewrote brand ids that were already assigned'
        );

        $this->assertSame($counts, $this->rowCounts(), 'the second run changed the number of rows');

        $probe = WebhookManagerDataFixture::handleProbe(0);

        $this->assertFalse($this->duplicateOutboundIsAccepted($probe, $this->defaultBrandId()));
        $this->assertTrue($this->duplicateOutboundIsAccepted($probe, $second));
    }

    /**
     * No `is_default` row, and the lowest brand id is not 1.
     *
     * brand-context creates its default brand with `insertOrIgnore`, so an
     * install whose `brands.handle` was already taken has no default row at
     * all; and ids are whatever that database's history made them. The
     * migration must stamp the brand that exists.
     */
    public function test_it_stamps_the_brand_that_exists_rather_than_the_literal_one(): void
    {
        $this->populatedPreBrandInstall();

        $this->isolated()->table('brands')->delete();
        $this->isolated()->table('brands')->insert([
            'id' => 7,
            'handle' => 'chorleiter',
            'name' => 'Chorleiter',
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migratePath($this->brandMigration());

        foreach (WebhookManagerDataFixture::tables() as $table) {
            $rows = $this->isolated()->table($table)->count();

            $this->assertGreaterThan(0, $rows, "{$table} was empty, so it proves nothing");

            $this->assertSame(
                $rows,
                $this->isolated()->table($table)->where('brand_id', 7)->count(),
                "{$table} was not assigned to the only brand this install has"
            );

            $this->assertSame(
                0,
                $this->isolated()->table($table)->where('brand_id', 1)->count(),
                "{$table} carries brand_id 1 — an id no brand in this database has"
            );
        }
    }

    /**
     * No brand at all. The migration stops and names what is missing.
     */
    public function test_it_refuses_to_run_when_there_is_no_brand_to_assign_rows_to(): void
    {
        $this->populatedPreBrandInstall();

        $this->isolated()->table('brands')->delete();

        $thrown = null;

        try {
            $this->migratePath($this->brandMigration());
        } catch (\RuntimeException $exception) {
            $thrown = $exception;
        }

        $this->assertNotNull($thrown, 'the migration invented a brand on an install that has none');

        // Deliberately the exact class, not an `instanceof`: QueryException is
        // itself a RuntimeException, and a driver error complaining about a
        // NOT NULL column would satisfy a looser check while telling the
        // operator nothing about what is actually missing.
        $this->assertSame(\RuntimeException::class, $thrown::class, $thrown->getMessage());

        $this->assertStringContainsString('brands', $thrown->getMessage());
        $this->assertStringContainsString('statamic-brand-context', $thrown->getMessage());

        // Stopping means stopping: nothing was added, nothing was stamped, and
        // the migration stays pending so it runs again once a brand exists.
        $this->assertFalse(
            $this->isolatedSchema()->hasColumn('webhook_outbounds', 'brand_id'),
            'the migration started converting the schema before checking it had a brand'
        );

        $this->assertNotContains(self::MIGRATION, $this->ranMigrations());
    }

    /**
     * The v1.2.0 schema — the last release before brand scoping — with a full
     * generation of rows in it.
     */
    private function populatedPreBrandInstall(): WebhookManagerDataFixture
    {
        $this->migratePath($this->releasedMigrations('v1.2.0'));

        $fixture = new WebhookManagerDataFixture($this->isolated());
        $fixture->seed(0);

        $this->assertFalse($this->isolatedSchema()->hasColumn('webhook_outbounds', 'brand_id'));

        return $fixture;
    }

    private function brandMigration(): string
    {
        return $this->currentMigrations().'/'.self::MIGRATION.'.php';
    }

    /**
     * Which brand every row belongs to, per table, keyed by row id.
     *
     * @return array<string, array<int, int|null>>
     */
    private function brandAssignments(): array
    {
        $assignments = [];

        foreach (WebhookManagerDataFixture::tables() as $table) {
            $assignments[$table] = $this->isolated()->table($table)
                ->orderBy('id')
                ->pluck('brand_id', 'id')
                ->map(fn ($id) => $id === null ? null : (int) $id)
                ->all();
        }

        return $assignments;
    }

    /**
     * @return array<string, int>
     */
    private function rowCounts(): array
    {
        return (new WebhookManagerDataFixture($this->isolated()))->counts();
    }

    private function defaultBrandId(): int
    {
        return (int) ($this->isolated()->table('brands')->where('is_default', true)->min('id')
            ?? $this->isolated()->table('brands')->min('id'));
    }

    private function secondBrandId(): int
    {
        $existing = $this->isolated()->table('brands')->where('handle', 'second')->value('id');

        if ($existing) {
            return (int) $existing;
        }

        return (int) $this->isolated()->table('brands')->insertGetId([
            'handle' => 'second',
            'name' => 'Second Brand',
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
