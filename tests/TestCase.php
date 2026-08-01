<?php

namespace Goldnead\WebhookManager\Tests;

use Goldnead\BrandContext\ServiceProvider as BrandContextServiceProvider;
use Goldnead\WebhookManager\WebhookManagerServiceProvider;
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
        }
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
