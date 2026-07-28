<?php

namespace Goldnead\WebhookManager\Tests\Migrations;

use Goldnead\WebhookManager\Tests\Fixtures\WebhookManagerDataFixture;
use Goldnead\WebhookManager\Tests\MigrationPathTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The migrations, run against a database that already holds data.
 *
 * Every migration check this addon had ran against empty tables, because every
 * bed it had was a fresh install: testbench creates the schema, the test writes
 * its own rows afterwards, and the migration never meets a row it did not
 * expect. That is not a thin spot in the coverage, it is the coverage pointing
 * away from the only case a migration can be wrong about — a table with rows in
 * it, created by an older release.
 *
 * What that let through is in the brand-scoping migration: a backfill with no
 * `where` that rewrites brand ids that already exist, seven unconditional
 * `alter table` statements that cannot be retried after an abort, and a default
 * brand id that fell back to the literal `1`. On an empty database none of the
 * three can be observed. On a populated one all three change what the tables
 * hold.
 *
 * Two things about how this file is written are deliberate.
 *
 * It names no migration file. It walks `database/migrations/` and seeds a fresh
 * generation of data into every table that exists *before each* migration, so a
 * migration added next year is covered the day it lands, and so no migration
 * only ever meets rows written under its own schema.
 *
 * And every assertion about the handle guarantee is behavioural. Nothing here
 * checks an index name or an exit code; it writes the row the constraint is
 * supposed to refuse and requires the database to refuse it — then writes the
 * row it is supposed to accept, in a second brand, and requires that one
 * through. A brand-scoped unique that is really still a global one passes the
 * first half and fails the second.
 */
class MigrationsWithExistingDataTest extends MigrationPathTestCase
{
    public function test_it_runs_every_migration_against_tables_that_already_hold_rows(): void
    {
        $fixture = new WebhookManagerDataFixture($this->isolated());
        $batch = 0;
        $seeded = [];

        $this->migrateStepwise($this->currentMigrations(), function () use ($fixture, &$batch, &$seeded): void {
            $fixture->seed($batch++);
            $seeded = $fixture->counts();
        });

        $probe = WebhookManagerDataFixture::handleProbe($batch - 1);

        // Same handle, same brand: refused.
        $this->assertFalse(
            $this->duplicateOutboundIsAccepted($probe, $this->defaultBrandId()),
            'the handle unique does not bite inside a brand after a stepwise migration over populated tables'
        );

        // Same handle, different brand: accepted. This is the half that a
        // migration which merely kept the global unique would fail.
        $this->assertTrue(
            $this->duplicateOutboundIsAccepted($probe, $this->secondBrandId()),
            'the handle unique is still global — a second brand cannot use a handle another brand already took'
        );

        // Nothing that was seeded may have gone missing on the way through.
        foreach ($seeded as $table => $count) {
            $this->assertGreaterThanOrEqual(
                $count,
                $this->isolated()->table($table)->count(),
                "rows disappeared from {$table}"
            );
        }

        foreach (WebhookManagerDataFixture::tables() as $table) {
            $this->assertSame(
                0,
                $this->isolated()->table($table)->whereNull('brand_id')->count(),
                "{$table} came out of the migration run with rows that carry no brand"
            );
        }
    }

    #[DataProvider('releasedVersions')]
    public function test_it_upgrades_a_populated_install_from_every_released_schema(string $version): void
    {
        // The install as it stood on that release, with its data.
        $this->migratePath($this->releasedMigrations($version));

        $fixture = new WebhookManagerDataFixture($this->isolated());

        // 3 outbounds, 2 inbounds, 2 rules, 2 templates, 5 deliveries, 5 logs
        // and 5 secret audits — every table populated, and every child pointed
        // at a parent that exists.
        $this->assertSame(24, $fixture->seed(0), "the {$version} schema did not take a full generation of rows");

        $before = $fixture->counts();

        // Then the upgrade, with the tables filling up further as it goes.
        $batch = 1;
        $this->migrateStepwise($this->currentMigrations(), function () use ($fixture, &$batch): void {
            $fixture->seed($batch++);
        });

        $probe = WebhookManagerDataFixture::handleProbe(0);

        $this->assertFalse(
            $this->duplicateOutboundIsAccepted($probe, $this->defaultBrandId()),
            "the handle unique does not bite inside a brand after upgrading a populated {$version} install"
        );

        $this->assertTrue(
            $this->duplicateOutboundIsAccepted($probe, $this->secondBrandId()),
            "a second brand cannot use an existing handle after upgrading a populated {$version} install"
        );

        // Nothing that was there before may have gone missing.
        foreach ($before as $table => $count) {
            $this->assertGreaterThanOrEqual(
                $count,
                $this->isolated()->table($table)->count(),
                "rows disappeared from {$table} while upgrading from {$version}"
            );
        }

        foreach (WebhookManagerDataFixture::tables() as $table) {
            $this->assertSame(
                0,
                $this->isolated()->table($table)->whereNull('brand_id')->count(),
                "{$table} still holds rows with no brand after upgrading from {$version}"
            );
        }
    }

    /**
     * The published schemas an install can be upgrading from, kept as the
     * migration files those tags actually shipped (`git show <tag>:…`) in
     * tests/Fixtures/released-migrations/. v1.2.0 is the last release before
     * brand scoping — the only shape where the conversion has real work to do —
     * and v1.7.2 is the current one, where it must do nothing at all.
     *
     * @return array<string, array{string}>
     */
    public static function releasedVersions(): array
    {
        return [
            'v1.2.0 (before brand scoping)' => ['v1.2.0'],
            'v1.7.2 (current)' => ['v1.7.2'],
        ];
    }

    private function defaultBrandId(): int
    {
        return (int) $this->isolated()->table('brands')->where('is_default', true)->min('id');
    }

    /**
     * A second brand, created on demand. Single-brand installs are the norm, so
     * the tenant a handle collision needs is not there until a test asks for it.
     */
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
