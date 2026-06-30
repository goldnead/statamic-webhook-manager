<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Repositories\Eloquent\EloquentOutboundWebhookRepository;
use Goldnead\WebhookManager\Repositories\FlatFile\FlatFileOutboundWebhookRepository;
use Goldnead\WebhookManager\Storage\FileStore;
use Goldnead\WebhookManager\Storage\StorageDriverManager;
use Goldnead\WebhookManager\Storage\StorageMigrator;
use Goldnead\WebhookManager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

class StorageDriverManagerTest extends TestCase
{
    use RefreshDatabase;

    protected string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = sys_get_temp_dir().'/wm-dm-'.uniqid();
        config()->set('webhook-manager.storage.flat.path', $this->path);
        $this->app->forgetInstance(FileStore::class);
        // Start from a clean slate — the persisted override lives in storage/.
        $this->manager()->clearOverride();
    }

    protected function tearDown(): void
    {
        // Critical: the override file is in shared storage_path; leaking it
        // would flip the driver for every later test.
        $this->manager()->clearOverride();
        if (isset($this->path) && File::isDirectory($this->path)) {
            File::deleteDirectory($this->path);
        }
        parent::tearDown();
    }

    protected function manager(): StorageDriverManager
    {
        return $this->app->make(StorageDriverManager::class);
    }

    public function test_defaults_to_the_configured_driver(): void
    {
        config()->set('webhook-manager.storage.driver', 'eloquent');
        $this->assertSame('eloquent', $this->manager()->current());
        $this->assertSame(StorageDriverManager::SOURCE_CONFIG, $this->manager()->source());

        config()->set('webhook-manager.storage.driver', 'flat');
        $this->assertSame('flat', $this->manager()->current());
    }

    public function test_persisted_override_takes_precedence_over_config(): void
    {
        config()->set('webhook-manager.storage.driver', 'eloquent');

        $this->manager()->setDriver('flat');

        $this->assertSame('flat', $this->manager()->current());
        $this->assertSame(StorageDriverManager::SOURCE_CONTROL_PANEL, $this->manager()->source());
        $this->assertFileExists($this->manager()->settingsPath());
    }

    public function test_clear_override_falls_back_to_config(): void
    {
        config()->set('webhook-manager.storage.driver', 'eloquent');
        $this->manager()->setDriver('flat');
        $this->manager()->clearOverride();

        $this->assertSame('eloquent', $this->manager()->current());
        $this->assertSame(StorageDriverManager::SOURCE_CONFIG, $this->manager()->source());
    }

    public function test_invalid_driver_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->manager()->setDriver('redis');
    }

    public function test_switching_driver_flips_the_resolved_repository_and_serves_migrated_data(): void
    {
        config()->set('webhook-manager.storage.driver', 'eloquent');

        OutboundWebhook::create([
            'name' => 'CP Switch', 'handle' => 'cp-switch', 'enabled' => true,
            'trigger_type' => 'entry.published', 'url' => 'https://example.com',
            'method' => 'POST', 'auth_type' => 'none', 'payload_type' => 'raw_json',
            'payload_template' => '{}',
        ]);

        // Default driver resolves the Eloquent repository.
        $this->assertInstanceOf(
            EloquentOutboundWebhookRepository::class,
            $this->app->make(OutboundWebhookRepositoryInterface::class),
        );

        // This is exactly what SettingsController::switchStorage does.
        $this->app->make(StorageMigrator::class)->migrate('eloquent', 'flat');
        $this->manager()->setDriver('flat');

        // Now the contract resolves the flat repo and serves the migrated record.
        $repo = $this->app->make(OutboundWebhookRepositoryInterface::class);
        $this->assertInstanceOf(FlatFileOutboundWebhookRepository::class, $repo);
        $this->assertSame(1, $repo->all()->count());
        $this->assertSame('CP Switch', $repo->findByHandle('cp-switch')->name);
        $this->assertFileExists($this->path.'/outbound/cp-switch.yaml');
    }
}
