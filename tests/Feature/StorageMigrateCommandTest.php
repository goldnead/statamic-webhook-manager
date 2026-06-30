<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Console\Commands\StorageMigrateCommand;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Storage\FileStore;
use Goldnead\WebhookManager\Tests\TestCase;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class StorageMigrateCommandTest extends TestCase
{
    use RefreshDatabase;

    protected string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = sys_get_temp_dir().'/wm-migrate-'.uniqid();
        config()->set('webhook-manager.storage.flat.path', $this->path);
        $this->app->forgetInstance(FileStore::class);

        // Statamic registers addon commands during its deferred boot, which
        // testbench doesn't fire — register the one under test directly.
        $this->app->make(Kernel::class)->registerCommand($this->app->make(StorageMigrateCommand::class));
    }

    protected function tearDown(): void
    {
        if (isset($this->path) && File::isDirectory($this->path)) {
            File::deleteDirectory($this->path);
        }
        parent::tearDown();
    }

    public function test_migrates_database_config_to_flat_files_preserving_ids(): void
    {
        $hook = OutboundWebhook::create([
            'name' => 'Prod Hook', 'handle' => 'prod-hook', 'enabled' => true,
            'trigger_type' => 'entry.published', 'url' => 'https://example.com/x',
            'method' => 'POST', 'auth_type' => 'none', 'payload_type' => 'raw_json',
            'payload_template' => '{}',
        ]);

        Artisan::call('webhook-manager:storage:migrate', ['--from' => 'eloquent', '--to' => 'flat']);

        $file = $this->path.'/outbound/prod-hook.yaml';
        $this->assertFileExists($file);

        $data = app(FileStore::class)->readYaml('outbound/prod-hook.yaml');
        $this->assertSame($hook->id, (int) $data['id'], 'id is preserved');
        $this->assertSame($hook->uuid, $data['uuid'], 'uuid is preserved');
    }

    public function test_round_trips_back_to_eloquent(): void
    {
        $hook = OutboundWebhook::create([
            'name' => 'Round Trip', 'handle' => 'round-trip', 'enabled' => true,
            'trigger_type' => 'entry.saved', 'url' => 'https://example.com/y',
            'method' => 'POST', 'auth_type' => 'none', 'payload_type' => 'raw_json',
            'payload_template' => '{}',
        ]);
        $originalId = $hook->id;
        $originalUuid = $hook->uuid;

        Artisan::call('webhook-manager:storage:migrate', ['--from' => 'eloquent', '--to' => 'flat']);

        // Wipe the DB copy, then migrate the flat data back in.
        OutboundWebhook::query()->delete();
        $this->assertSame(0, OutboundWebhook::count());

        Artisan::call('webhook-manager:storage:migrate', ['--from' => 'flat', '--to' => 'eloquent']);

        $restored = OutboundWebhook::first();
        $this->assertNotNull($restored);
        $this->assertSame($originalId, $restored->id, 'id survives the round trip');
        $this->assertSame($originalUuid, $restored->uuid);
        $this->assertSame('Round Trip', $restored->name);
    }

    public function test_dry_run_writes_nothing(): void
    {
        OutboundWebhook::create([
            'name' => 'Dry', 'handle' => 'dry', 'enabled' => true,
            'trigger_type' => 'entry.published', 'url' => 'https://example.com/z',
            'method' => 'POST', 'auth_type' => 'none', 'payload_type' => 'raw_json',
            'payload_template' => '{}',
        ]);

        Artisan::call('webhook-manager:storage:migrate', ['--from' => 'eloquent', '--to' => 'flat', '--dry-run' => true]);

        $this->assertFileDoesNotExist($this->path.'/outbound/dry.yaml');
    }
}
