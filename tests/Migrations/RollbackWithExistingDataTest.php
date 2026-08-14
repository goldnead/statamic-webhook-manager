<?php

namespace Goldnead\WebhookManager\Tests\Migrations;

use Goldnead\WebhookManager\Tests\Fixtures\WebhookManagerDataFixture;
use Goldnead\WebhookManager\Tests\MigrationPathTestCase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/**
 * `php artisan migrate:rollback`, against a database that already holds rows.
 *
 * The forward path has a bed of its own (MigrationsWithExistingDataTest); this
 * is the other direction, and it was never covered. The brand-scoping migration
 * exists to let two brands hold the same handle, so its `down()` has to put a
 * constraint back that the data it is looking at may no longer satisfy — the one
 * shape a rollback can be wrong about, and one that only appears once somebody
 * has actually used the feature.
 *
 * Nothing here reads an index name. A rollback is judged by what the database
 * accepts afterwards and by whether the tables it did not finish converting were
 * left alone.
 */
class RollbackWithExistingDataTest extends MigrationPathTestCase
{
    /**
     * The rollback cannot restore a global handle unique over data that holds
     * the same handle twice, and it may not decide for the operator which of the
     * two webhooks survives — a row here carries a destination URL and the
     * credential that signs it. So it refuses, before the first `alter table`,
     * and names what is in the way.
     */
    public function test_it_refuses_to_roll_back_while_two_brands_share_a_handle(): void
    {
        $this->installHead();

        $handle = WebhookManagerDataFixture::handleProbe(0);
        $this->giveSecondBrandTheHandle($handle);

        $thrown = $this->rollbackBrandScoping();

        $this->assertNotNull(
            $thrown,
            'migrate:rollback went through while two brands share a handle — the global unique it restores cannot hold over that data'
        );

        // A QueryException means it started the conversion and died inside an
        // `alter table`, which is the failure mode, not the fix: no engine rolls
        // DDL back, so the install is left half-converted.
        $this->assertNotInstanceOf(
            QueryException::class,
            $thrown,
            'the rollback died inside an `alter table` instead of refusing before it: '.$thrown->getMessage()
        );

        $message = $thrown->getMessage();

        $this->assertStringContainsString('webhook_outbounds', $message, 'the refusal does not name the table in the way');
        $this->assertStringContainsString($handle, $message, 'the refusal does not name the handle in the way');
        $this->assertStringContainsString('migrate:rollback', $message, 'the refusal does not say what to run once it is resolved');

        // And nothing was changed: brand scoping is still fully in place.
        foreach (WebhookManagerDataFixture::tables() as $table) {
            $this->assertTrue(
                $this->isolatedSchema()->hasColumn($table, 'brand_id'),
                "{$table} lost brand_id to a rollback that was supposed to refuse"
            );
        }

        $this->assertFalse(
            $this->duplicateOutboundIsAccepted($handle, $this->defaultBrandId()),
            'the handle unique stopped biting inside a brand after a refused rollback'
        );

        // A third brand can still take the handle, so the unique that is in
        // place is the brand-scoped one and not something weaker.
        $this->assertTrue(
            $this->duplicateOutboundIsAccepted($handle, $this->brandId('third')),
            'the brand-scoped unique was dropped by a rollback that was supposed to refuse'
        );

        $this->assertSame(
            2,
            $this->isolated()->table('webhook_outbounds')->where('handle', $handle)->count(),
            'the rollback removed one of the two colliding webhooks instead of refusing'
        );
    }

    /**
     * With the collision resolved the same command goes through, and the global
     * unique it restores really bites.
     */
    public function test_it_rolls_back_a_populated_install_whose_handles_are_globally_unique(): void
    {
        $this->installHead();

        $handle = WebhookManagerDataFixture::handleProbe(0);

        $this->assertNull($this->rollbackBrandScoping(), 'a rollback with no handle collision must go through');

        foreach (WebhookManagerDataFixture::tables() as $table) {
            $this->assertFalse(
                $this->isolatedSchema()->hasColumn($table, 'brand_id'),
                "{$table} still carries brand_id after the rollback"
            );
        }

        // The pre-brand guarantee is back: one handle, one webhook, full stop.
        $this->assertFalse(
            $this->duplicateOutboundIsAccepted($handle),
            'the rollback did not restore the global handle unique'
        );

        foreach (WebhookManagerDataFixture::tables() as $table) {
            $this->assertGreaterThan(0, $this->isolated()->table($table)->count(), "the rollback emptied {$table}");
        }
    }

    /**
     * Every `down()` in the addon, over populated tables, in one go. A rollback
     * that throws halfway leaves tables behind, so the tables being gone is the
     * assertion.
     */
    public function test_every_migration_rolls_back_off_a_populated_install(): void
    {
        $this->installHead();

        Artisan::call('migrate:reset', [
            '--database' => self::CONNECTION,
            '--path' => $this->currentMigrations(),
            '--realpath' => true,
            '--force' => true,
        ]);

        foreach (WebhookManagerDataFixture::tables() as $table) {
            $this->assertFalse(
                $this->isolatedSchema()->hasTable($table),
                "{$table} survived a full rollback"
            );
        }
    }

    /**
     * Head schema with one full generation of rows in every table.
     */
    private function installHead(): void
    {
        $this->migrateStepwise($this->currentMigrations());

        (new WebhookManagerDataFixture($this->isolated()))->seed(0);
    }

    /**
     * A second brand's copy of an existing outbound webhook — the row the whole
     * brand-scoping migration was written to allow.
     */
    private function giveSecondBrandTheHandle(string $handle): void
    {
        $existing = (array) $this->isolated()->table('webhook_outbounds')
            ->where('handle', $handle)
            ->orderBy('id')
            ->firstOrFail();

        unset($existing['id']);

        $existing['uuid'] = (string) Str::uuid();
        $existing['brand_id'] = $this->secondBrandId();

        $this->isolated()->table('webhook_outbounds')->insert($existing);
    }

    /** The migration under test here, by name rather than by position. */
    private const BRAND_MIGRATION = '2026_07_24_100003_add_brand_id_to_webhook_manager_tables';

    /**
     * Roll back to and including the brand-scoping migration, and hand back
     * whatever it threw, or null.
     *
     * The step count is computed from the migration folder rather than being
     * `1`. It was `1` while brand scoping happened to be the newest migration,
     * and the moment a later one was added (`create_webhook_settings_table`)
     * that `1` silently pointed at the new migration instead — these tests then
     * asserted about a rollback that never touched brand scoping at all, and
     * failed for a reason that had nothing to do with what they are named after.
     */
    private function rollbackBrandScoping(): ?\Throwable
    {
        try {
            Artisan::call('migrate:rollback', [
                '--database' => self::CONNECTION,
                '--path' => $this->currentMigrations(),
                '--realpath' => true,
                '--force' => true,
                '--step' => $this->stepsBackToBrandScoping(),
            ]);
        } catch (\Throwable $e) {
            return $e;
        }

        return null;
    }

    /** How many migrations sit at or after the brand-scoping one. */
    private function stepsBackToBrandScoping(): int
    {
        $names = array_map(
            fn (string $file) => basename($file, '.php'),
            glob($this->currentMigrations().'/*.php') ?: [],
        );

        sort($names);

        $position = array_search(self::BRAND_MIGRATION, $names, true);

        $this->assertNotFalse($position, 'the brand-scoping migration was renamed; this whole file points at it by name.');

        return count($names) - (int) $position;
    }

    private function defaultBrandId(): int
    {
        return (int) $this->isolated()->table('brands')->where('is_default', true)->min('id');
    }

    private function secondBrandId(): int
    {
        return $this->brandId('second');
    }

    /**
     * A further brand, created on demand. Single-brand installs are the norm, so
     * the tenants a handle collision needs are not there until a test asks.
     */
    private function brandId(string $handle): int
    {
        $existing = $this->isolated()->table('brands')->where('handle', $handle)->value('id');

        if ($existing) {
            return (int) $existing;
        }

        return (int) $this->isolated()->table('brands')->insertGetId([
            'handle' => $handle,
            'name' => ucfirst($handle).' Brand',
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
