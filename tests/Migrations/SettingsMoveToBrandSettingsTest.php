<?php

namespace Goldnead\WebhookManager\Tests\Migrations;

use Goldnead\BrandContext\Models\BrandSetting;
use Goldnead\BrandContext\Settings\SettingsManager;
use Goldnead\WebhookManager\Tests\MigrationPathTestCase;
use Illuminate\Support\Facades\Artisan;

/**
 * The upgrade that moves this addon's settings into the suite's shared table.
 *
 * The settings screen is no longer this package's, and the values an operator
 * saved through the old one sit in `webhook_settings`, which nothing reads any
 * more. If they do not come across, an install that had customised its retry or
 * logging behaviour comes back up on the packaged defaults — silently, because
 * the new screen looks perfectly healthy showing them.
 *
 * Run against the isolated migration bed rather than the suite's own database:
 * these tests need the tables to have rows in them *before* the migration runs,
 * which is the one state the suite's already-migrated-to-head connection cannot
 * produce.
 */
class SettingsMoveToBrandSettingsTest extends MigrationPathTestCase
{
    private const MOVE = '2026_09_06_000001_move_webhook_settings_to_brand_settings';

    /**
     * Install every migration up to (not including) the move, so the old table
     * exists and can be filled, then run the move on top.
     *
     * @param  array<string, mixed>  $settings  key => value, stored as JSON
     */
    private function migrateWithStoredSettings(array $settings): void
    {
        $this->migrateStepwise($this->currentMigrations(), function (string $name) use ($settings) {
            if ($name !== self::MOVE) {
                return;
            }

            foreach ($settings as $key => $value) {
                $this->isolated()->table('webhook_settings')->insert([
                    'key' => $key,
                    'value' => json_encode($value),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    private function defaultBrandId(): int
    {
        return (int) $this->isolated()->table('brands')
            ->where('handle', config('brand-context.default_handle', 'default'))
            ->value('id');
    }

    public function test_it_moves_a_stored_setting_onto_the_default_brand(): void
    {
        $this->migrateWithStoredSettings(['retry.max_attempts' => 9]);

        $row = $this->isolated()->table('brand_settings')
            ->where('namespace', 'webhook-manager')
            ->where('key', 'retry.max_attempts')
            ->first();

        $this->assertNotNull($row, 'The stored override did not reach `brand_settings` — the install comes back up on the packaged default.');
        $this->assertSame($this->defaultBrandId(), (int) $row->brand_id);

        // The value has to survive as the type it was. `retry.max_attempts` is
        // read into arithmetic and into a strict comparison, and a `"9"` there
        // fails somewhere else entirely.
        $this->assertSame(9, json_decode($row->value, true));
    }

    public function test_the_moved_value_is_the_one_the_config_serves(): void
    {
        $this->migrateWithStoredSettings(['retry.max_attempts' => 9]);

        // The point of the whole layer: a stored row is not a record of an
        // intention, it is what `config('webhook-manager.…')` answers — which
        // is what the delivery engine, the retry planner, the jobs and the
        // console commands all read.
        //
        // The row the migration actually produced drives this, column for
        // column, rather than a hand-typed copy of what it is supposed to
        // produce — a test that writes its own row proves the reader and
        // nothing about the migration. It is replayed into the suite's own
        // database instead of pointing the settings layer at the isolated one:
        // that connection is a temp file this bed deletes between tests, and
        // switching `database.default` onto it takes the whole application with
        // it, teardown included.
        $moved = $this->isolated()->table('brand_settings')
            ->where('namespace', 'webhook-manager')
            ->where('key', 'retry.max_attempts')
            ->first();

        BrandSetting::query()->create([
            'brand_id' => app('brand-context')->currentId(),
            'namespace' => $moved->namespace,
            'key' => $moved->key,
            'value' => json_decode($moved->value, true),
        ]);

        $settings = $this->app->make(SettingsManager::class);
        $settings->forget('webhook-manager');
        $settings->apply(force: true);

        $this->assertSame(9, config('webhook-manager.retry.max_attempts'));
    }

    public function test_it_leaves_the_old_table_in_place(): void
    {
        $this->migrateWithStoredSettings(['retry.max_attempts' => 9]);

        // Kept for one minor version on purpose. A rollback to the previous
        // release re-registers the old screen, which reads this table — and if
        // the move had dropped it, that rollback would come back up with every
        // setting reset and nothing saying so.
        $this->assertTrue($this->isolatedSchema()->hasTable('webhook_settings'));
        $this->assertSame(1, $this->isolated()->table('webhook_settings')->count());
    }

    public function test_running_it_twice_changes_nothing(): void
    {
        $this->migrateWithStoredSettings(['retry.max_attempts' => 9]);

        // Re-running the file by hand is what a repaired half-finished upgrade
        // looks like: the migration is pending again over data it has already
        // moved. A second row would break the unique index; an overwrite would
        // undo whatever the operator changed on the new screen in between.
        $this->isolated()->table('brand_settings')
            ->where('namespace', 'webhook-manager')
            ->where('key', 'retry.max_attempts')
            ->update(['value' => json_encode(4)]);

        $this->isolated()->table('migrations')->where('migration', self::MOVE)->delete();
        $this->migratePath($this->currentMigrations().'/'.self::MOVE.'.php');

        $rows = $this->isolated()->table('brand_settings')
            ->where('namespace', 'webhook-manager')
            ->where('key', 'retry.max_attempts')
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame(4, json_decode($rows->first()->value, true), 'The second run overwrote a value the operator had changed since.');
    }

    public function test_it_leaves_a_secret_bearing_key_behind(): void
    {
        // `brand_settings` is in every database backup and every export. The
        // settings definition never offered a credential — the alert webhook
        // URL and the signing secrets are `.env` and the secret store — so this
        // only ever matches a row somebody wrote by hand, which is exactly the
        // row that must not be copied into a table that travels.
        $this->migrateWithStoredSettings([
            'retry.max_attempts' => 9,
            'alerts.slack.webhook_url' => 'https://hooks.slack.test/T000/B000/xoxb-secret',
        ]);

        $moved = $this->isolated()->table('brand_settings')
            ->where('namespace', 'webhook-manager')
            ->pluck('key')
            ->all();

        $this->assertSame(['retry.max_attempts'], $moved);

        // And it is still where it was, not deleted along the way.
        $this->assertTrue(
            $this->isolated()->table('webhook_settings')->where('key', 'alerts.slack.webhook_url')->exists()
        );
    }

    public function test_it_does_nothing_when_there_is_nothing_to_move(): void
    {
        // The overwhelmingly common upgrade: an install that never opened the
        // settings screen. It must not acquire a row, and it must not fail.
        $this->migrateWithStoredSettings([]);

        $this->assertSame(
            0,
            $this->isolated()->table('brand_settings')->where('namespace', 'webhook-manager')->count()
        );
    }

    public function test_rolling_it_back_takes_the_moved_rows_out_again(): void
    {
        $this->migrateWithStoredSettings(['retry.max_attempts' => 9]);

        Artisan::call('migrate:rollback', [
            '--database' => self::CONNECTION,
            '--path' => $this->currentMigrations().'/'.self::MOVE.'.php',
            '--realpath' => true,
            '--force' => true,
            '--step' => 1,
        ]);

        $this->assertSame(
            0,
            $this->isolated()->table('brand_settings')->where('namespace', 'webhook-manager')->count()
        );

        // The old table is the one the rolled-back release reads, so it has to
        // still hold the value.
        $this->assertSame(1, $this->isolated()->table('webhook_settings')->count());
    }
}
