<?php

namespace Goldnead\WebhookManager\Tests;

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
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('webhook-manager.queue.connection', 'sync');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Statamic registers the addon's web routes via Statamic::pushWebRoutes()
     * during its own boot phase, which doesn't fire in a stripped-down
     * orchestra/testbench environment. Load the inbound endpoint routes
     * directly so feature tests that POST to /!/webhooks/inbound/{handle}
     * can hit the real controller instead of getting 404.
     */
    protected function defineRoutes($router): void
    {
        require __DIR__.'/../routes/inbound.php';
    }
}
