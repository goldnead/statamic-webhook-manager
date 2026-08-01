<?php

namespace Goldnead\WebhookManager\Tests;

use Goldnead\BrandContext\ServiceProvider as BrandContextServiceProvider;
use Goldnead\WebhookManager\WebhookManagerServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Statamic addons defer all boot work (bindings, registries,
        // permissions, navigation) into bootAddon() which Statamic only
        // fires from Statamic::booted() during a full Statamic site boot.
        // testbench doesn't run that boot lifecycle, so resolve our own
        // ServiceProvider and invoke bootAddon() directly.
        $provider = $this->app->getProvider(WebhookManagerServiceProvider::class);
        if ($provider) {
            $provider->bootAddon();
            $this->bootConsoleSurface($provider);
        }
    }

    /**
     * testbench rolls this addon's migrations back when the application is torn
     * down, and the brand-scoping migration's `down()` restores the global
     * `handle` unique that existed before brands. A test that exercises the
     * feature — two brands, one handle — therefore leaves the test bed holding
     * rows that the rollback cannot keep, and it refuses (see the migration and
     * Migrations\RollbackWithExistingDataTest). That refusal is correct; an
     * operator gets it too, and resolves it by removing one side of every
     * collision before rolling back. The bed does the same thing here, after the
     * test's assertions have run.
     *
     * Only visible under MySQL. The default SQLite run is in-memory and its
     * database is gone before the rollback ever reads a row, which is why the
     * rollback path had no coverage at all until the MySQL leg was added.
     */
    protected function tearDown(): void
    {
        $this->removeCrossBrandHandleCollisions();

        parent::tearDown();
    }

    /**
     * Keep the lowest id per handle in each root table, drop the rest.
     */
    protected function removeCrossBrandHandleCollisions(): void
    {
        $tables = ['webhook_outbounds', 'webhook_inbounds', 'webhook_rules', 'webhook_templates'];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'brand_id')) {
                continue;
            }

            $keep = DB::table($table)->selectRaw('min(id) as id')->groupBy('handle')->pluck('id')->all();

            if ($keep !== []) {
                DB::table($table)->whereNotIn('id', $keep)->delete();
            }
        }
    }

    /**
     * Console commands and the schedule entry are registered by
     * AddonServiceProvider::boot(), but only from inside the Statamic::booted()
     * callback that testbench never fires — the same reason bootAddon() is
     * called by hand above.
     *
     * The parent's bootCommands() cannot be reused here: it resolves the addon
     * out of Statamic's manifest, which needs a real installed package. The
     * declared $commands array is read directly instead.
     *
     * Without this, `webhook-manager:*` does not exist in the suite and the
     * scheduler is empty, so any test asserting on either would pass or fail
     * for the wrong reason.
     */
    protected function bootConsoleSurface(WebhookManagerServiceProvider $provider): void
    {
        $commands = new \ReflectionProperty($provider, 'commands');
        $provider->commands($commands->getValue($provider));

        $provider->schedule($this->app->make(Schedule::class));
    }

    protected function getPackageProviders($app): array
    {
        return [
            // brand-context first: it ships the `brands` table + default brand
            // that the addon's brand_id backfill migration depends on.
            BrandContextServiceProvider::class,
            WebhookManagerServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Required for the encrypted `auth_config` cast on InboundEndpoint
        // (Crypt::encrypt/decrypt) — without it any inbound feature test
        // that creates an endpoint dies with MissingAppKeyException.
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', $this->testingConnection());
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('webhook-manager.queue.connection', 'sync');
    }

    /**
     * In-memory SQLite by default, so the suite keeps running anywhere with no
     * setup. Set `DB_DRIVER=mysql` to point the identical suite at a real MySQL
     * server instead — see phpunit.mysql.xml.
     *
     * SQLite is not a substitute for that run. It has no InnoDB key-length
     * limit, no utf8mb4 byte arithmetic and no fixed column widths, which is
     * precisely why a fully green suite let an unbuildable index reach
     * production in statamic-notifications v1.0.3.
     */
    protected function testingConnection(): array
    {
        if (env('DB_DRIVER', 'sqlite') !== 'mysql') {
            return [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ];
        }

        return [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'webhook_manager_test'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        // brand-context migrations first — they create the `brands` table + the
        // default brand that the addon's brand_id backfill migration reads.
        // testbench's loadMigrationsFrom scopes each migrate run to one path, so
        // the provider's own path is not run automatically here.
        $this->loadMigrationsFrom(
            dirname((new \ReflectionClass(BrandContextServiceProvider::class))->getFileName(), 2).'/database/migrations'
        );

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
