<?php

namespace Goldnead\WebhookManager\Tests;

use Goldnead\BrandContext\ServiceProvider as BrandContextServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A bed for migrating a database by hand, from any released schema forward.
 *
 * The rest of the suite runs against a database testbench has already migrated
 * to head, which is the one shape a migration can never be wrong about.
 * Everything here needs the opposite: an empty database, an arbitrary earlier
 * release installed into it, rows put in, and then the migrations run one at a
 * time with the tables no longer empty.
 *
 * That cannot share the suite's connection. The suite's `testing` connection is
 * in-memory SQLite that testbench migrates to head in setUp, so there is no
 * earlier release to install into it and no way to take it back apart; and
 * under MySQL a `migrate` run against it would collide with the schema every
 * other test depends on. So these tests get a connection of their own, outside
 * anything the test bed manages: a temp-file SQLite database by default, and a
 * second throwaway schema beside the configured one when the suite is pointed
 * at MySQL (see phpunit.mysql.xml). It is torn down between tests either way.
 *
 * `brands` is a hard precondition rather than part of the schema under test:
 * the brand-scoping migration assigns every pre-brand row to the default brand
 * and refuses to invent one. So each reset installs goldnead/statamic-brand-
 * context's migrations first, exactly as TestCase::defineDatabaseMigrations()
 * does for the ordinary suite, and leaves this addon's own tables absent.
 */
abstract class MigrationPathTestCase extends TestCase
{
    /**
     * The name of the isolated connection these tests migrate.
     */
    public const CONNECTION = 'migration_path';

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetIsolatedDatabase();
    }

    protected function tearDown(): void
    {
        $this->dropIsolatedSqliteFile();

        parent::tearDown();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.connections.'.self::CONNECTION, $this->isolatedConnection());

        // A server-level handle with no database selected, used for nothing but
        // `create database`. Issuing that on the suite's own connection would
        // mean the schema every other test shares gets DDL from these ones.
        $app['config']->set('database.connections.'.self::CONNECTION.'_server', [
            ...$this->isolatedConnection(),
            'database' => null,
        ]);
    }

    /**
     * Mirrors TestCase::testingConnection(), so these tests exercise the same
     * engine the rest of the run does — including the MySQL run, where the
     * index and column rules SQLite does not have are the whole point.
     *
     * @return array<string, mixed>
     */
    protected function isolatedConnection(): array
    {
        if (env('DB_DRIVER', 'sqlite') !== 'mysql') {
            return [
                'driver' => 'sqlite',
                'database' => $this->isolatedSqlitePath(),
                'prefix' => '',
                'foreign_key_constraints' => true,
            ];
        }

        return [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $this->isolatedMysqlDatabase(),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ];
    }

    protected function isolatedMysqlDatabase(): string
    {
        return env('DB_DATABASE', 'webhook_manager_test').'_migration_path';
    }

    protected function isolatedSqlitePath(): string
    {
        return sys_get_temp_dir().'/webhook-manager-migration-path-'.getmypid().'.sqlite';
    }

    /**
     * Empty database, brand-context installed, nothing of this addon's own.
     */
    protected function resetIsolatedDatabase(): void
    {
        if (env('DB_DRIVER', 'sqlite') !== 'mysql') {
            $this->dropIsolatedSqliteFile();
            touch($this->isolatedSqlitePath());
        } else {
            DB::connection(self::CONNECTION.'_server')->statement(
                'create database if not exists `'.$this->isolatedMysqlDatabase().'` '
                .'character set utf8mb4 collate utf8mb4_unicode_ci'
            );

            DB::purge(self::CONNECTION.'_server');
        }

        DB::purge(self::CONNECTION);

        Schema::connection(self::CONNECTION)->dropAllTables();

        DB::purge(self::CONNECTION);

        $this->migratePath($this->brandContextMigrations());
    }

    protected function dropIsolatedSqliteFile(): void
    {
        if (env('DB_DRIVER', 'sqlite') !== 'mysql') {
            DB::purge(self::CONNECTION);

            if (file_exists($this->isolatedSqlitePath())) {
                @unlink($this->isolatedSqlitePath());
            }
        }
    }

    /**
     * Where brand-context keeps its migrations, resolved off the installed
     * package rather than written down as a path — the same reflection
     * TestCase::defineDatabaseMigrations() uses.
     */
    protected function brandContextMigrations(): string
    {
        return dirname((new \ReflectionClass(BrandContextServiceProvider::class))->getFileName(), 2)
            .'/database/migrations';
    }

    /**
     * Run every not-yet-run migration in a directory — or one file — against
     * the isolated connection. Failures are not swallowed: the point of these
     * tests is what happens when one throws.
     */
    protected function migratePath(string $path): void
    {
        Artisan::call('migrate', [
            '--database' => self::CONNECTION,
            '--path' => $path,
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    /**
     * Run the migrations in a directory one file at a time, handing control
     * back between each so a caller can put rows in first.
     *
     * @param  callable(string): void|null  $before  receives the migration name
     */
    protected function migrateStepwise(string $path, ?callable $before = null): void
    {
        foreach ($this->migrationFilesIn($path) as $file) {
            if ($before) {
                $before(basename($file, '.php'));
            }

            $this->migratePath($file);
        }
    }

    /**
     * @return list<string>
     */
    protected function migrationFilesIn(string $path): array
    {
        $files = glob(rtrim($path, '/').'/*.php') ?: [];

        sort($files);

        return $files;
    }

    protected function releasedMigrations(string $version): string
    {
        return __DIR__.'/Fixtures/released-migrations/'.$version;
    }

    protected function currentMigrations(): string
    {
        return __DIR__.'/../database/migrations';
    }

    protected function isolated(): \Illuminate\Database\Connection
    {
        return DB::connection(self::CONNECTION);
    }

    protected function isolatedSchema(): \Illuminate\Database\Schema\Builder
    {
        return Schema::connection(self::CONNECTION);
    }

    /**
     * The migration names the isolated database has recorded as run.
     *
     * @return list<string>
     */
    protected function ranMigrations(): array
    {
        if (! $this->isolatedSchema()->hasTable('migrations')) {
            return [];
        }

        return $this->isolated()->table('migrations')->pluck('migration')->all();
    }

    /**
     * Whether a second outbound webhook with the same handle can be written.
     *
     * This is the assertion the handle rework exists for, and it is
     * deliberately behavioural. "The migration ran" and "the constraint is
     * there" are not the same statement, and an index checked by name can exist
     * over the wrong columns, over a nullable column, or not bite at all. The
     * only thing that settles it is trying to write the row the constraint is
     * supposed to refuse — so nothing here reads an index name.
     *
     * The row it writes is a copy of a real seeded webhook with a fresh uuid,
     * optionally reassigned to another brand, which is exactly what a second
     * tenant creating a webhook called `crm-lead` looks like. Accepted rows are
     * removed again, so a probe leaves the table as it found it.
     */
    protected function duplicateOutboundIsAccepted(string $handle, ?int $brandId = null): bool
    {
        $existing = $this->isolated()->table('webhook_outbounds')
            ->where('handle', $handle)
            ->orderBy('id')
            ->first();

        if (! $existing) {
            throw new \RuntimeException("No outbound webhook with the handle [{$handle}] to duplicate.");
        }

        $row = collect((array) $existing)
            ->except('id')
            ->put('uuid', (string) \Illuminate\Support\Str::uuid())
            ->all();

        if ($brandId !== null && $this->isolatedSchema()->hasColumn('webhook_outbounds', 'brand_id')) {
            $row['brand_id'] = $brandId;
        }

        try {
            $id = $this->isolated()->table('webhook_outbounds')->insertGetId($row);
        } catch (\Illuminate\Database\QueryException) {
            return false;
        }

        $this->isolated()->table('webhook_outbounds')->where('id', $id)->delete();

        return true;
    }
}
