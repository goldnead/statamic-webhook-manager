<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface;
use Goldnead\WebhookManager\Contracts\Repositories\TemplateRepositoryInterface;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Actions\CreateOutboundWebhookAction;
use Goldnead\WebhookManager\Repositories\FlatFile\FlatFileOutboundWebhookRepository;
use Goldnead\WebhookManager\Storage\FileStore;
use Goldnead\WebhookManager\Tests\TestCase;
use Illuminate\Support\Facades\File;

class FlatFileStorageTest extends TestCase
{
    protected string $path;

    protected function setUp(): void
    {
        parent::setUp();

        // Point the flat-file store at an isolated temp directory and select
        // the flat driver, then drop any FileStore the bootstrap may have made
        // so it re-resolves against this path.
        $this->path = sys_get_temp_dir().'/wm-flat-'.uniqid();
        config()->set('webhook-manager.storage.driver', 'flat');
        config()->set('webhook-manager.storage.flat.path', $this->path);
        $this->app->forgetInstance(FileStore::class);
    }

    protected function tearDown(): void
    {
        if (isset($this->path) && File::isDirectory($this->path)) {
            File::deleteDirectory($this->path);
        }
        parent::tearDown();
    }

    protected function repo(): OutboundWebhookRepositoryInterface
    {
        return $this->app->make(OutboundWebhookRepositoryInterface::class);
    }

    public function test_driver_binding_resolves_flatfile_implementation(): void
    {
        $this->assertInstanceOf(FlatFileOutboundWebhookRepository::class, $this->repo());
        $this->assertInstanceOf(
            \Goldnead\WebhookManager\Repositories\FlatFile\FlatFileTemplateRepository::class,
            $this->app->make(TemplateRepositoryInterface::class),
        );
    }

    public function test_create_writes_yaml_and_assigns_stable_integer_ids(): void
    {
        $a = $this->repo()->create([
            'name' => 'Notify Slack', 'trigger_type' => 'entry.published',
            'url' => 'https://example.com/a', 'method' => 'POST',
        ]);
        $b = $this->repo()->create([
            'name' => 'Sync CRM', 'trigger_type' => 'entry.saved',
            'url' => 'https://example.com/b', 'method' => 'POST',
        ]);

        $this->assertSame(1, (int) $a->id);
        $this->assertSame(2, (int) $b->id);
        $this->assertNotEmpty($a->uuid);
        $this->assertFileExists($this->path.'/outbound/notify-slack.yaml');

        // find / findByHandle / findByUuid all resolve.
        $this->assertSame('Notify Slack', $this->repo()->find(1)->name);
        $this->assertSame(1, (int) $this->repo()->findByHandle('notify-slack')->id);
        $this->assertSame(1, (int) $this->repo()->findByUuid($a->uuid)->id);
        $this->assertSame(2, $this->repo()->all()->count());
    }

    public function test_array_fields_round_trip_as_native_yaml(): void
    {
        $hook = $this->repo()->create([
            'name' => 'Headers', 'trigger_type' => 'entry.published',
            'url' => 'https://example.com', 'method' => 'POST',
            'headers' => ['X-Token' => 'abc', 'Accept' => 'application/json'],
        ]);

        // Stored as a native YAML map (not a JSON string)...
        $raw = File::get($this->path.'/outbound/headers.yaml');
        $this->assertStringContainsString('X-Token: abc', $raw);

        // ...and rehydrates to a PHP array.
        $this->assertSame(['X-Token' => 'abc', 'Accept' => 'application/json'], $this->repo()->find($hook->id)->headers);
    }

    public function test_auth_config_is_encrypted_at_rest(): void
    {
        $hook = $this->repo()->create([
            'name' => 'Signed', 'trigger_type' => 'entry.published',
            'url' => 'https://example.com', 'method' => 'POST',
            'auth_type' => 'hmac', 'auth_config' => ['secret' => 'super-secret-value'],
        ]);

        $raw = File::get($this->path.'/outbound/signed.yaml');
        $this->assertStringNotContainsString('super-secret-value', $raw);

        // Decrypts transparently on read.
        $this->assertSame(['secret' => 'super-secret-value'], $this->repo()->find($hook->id)->auth_config);
    }

    public function test_update_persists_and_renames_file_on_handle_change(): void
    {
        $hook = $this->repo()->create([
            'name' => 'Old', 'handle' => 'old', 'trigger_type' => 'entry.published',
            'url' => 'https://example.com', 'method' => 'POST',
        ]);

        $hook->name = 'New';
        $hook->handle = 'new';
        $hook->url = 'https://example.com/changed';
        $saved = $this->repo()->save($hook);

        $this->assertSame(1, (int) $saved->id, 'id is preserved across rename');
        $this->assertFileExists($this->path.'/outbound/new.yaml');
        $this->assertFileDoesNotExist($this->path.'/outbound/old.yaml');
        $this->assertSame('https://example.com/changed', $this->repo()->find(1)->url);
        $this->assertSame(1, $this->repo()->all()->count());
    }

    public function test_delete_removes_the_file(): void
    {
        $hook = $this->repo()->create([
            'name' => 'Temp', 'trigger_type' => 'entry.published',
            'url' => 'https://example.com', 'method' => 'POST',
        ]);

        $this->assertTrue($this->repo()->delete($hook));
        $this->assertFileDoesNotExist($this->path.'/outbound/temp.yaml');
        $this->assertNull($this->repo()->find($hook->id));
    }

    public function test_active_for_trigger_filters_enabled_and_trigger(): void
    {
        $this->repo()->create(['name' => 'On', 'enabled' => true, 'trigger_type' => 'entry.published', 'url' => 'https://a.test', 'method' => 'POST']);
        $this->repo()->create(['name' => 'Off', 'enabled' => false, 'trigger_type' => 'entry.published', 'url' => 'https://b.test', 'method' => 'POST']);
        $this->repo()->create(['name' => 'Other', 'enabled' => true, 'trigger_type' => 'entry.saved', 'url' => 'https://c.test', 'method' => 'POST']);

        $matches = $this->repo()->activeForTrigger('entry.published');

        $this->assertSame(1, $matches->count());
        $this->assertSame('On', $matches->first()->name);
    }

    public function test_create_action_uses_the_flatfile_driver(): void
    {
        $hook = $this->app->make(CreateOutboundWebhookAction::class)([
            'name' => 'Via Action', 'trigger_type' => 'entry.published',
            'url' => 'https://example.com', 'method' => 'post',
        ]);

        $this->assertFileExists($this->path.'/outbound/via-action.yaml');
        $this->assertSame('POST', $hook->method, 'normalize() still applied');
        $this->assertSame(1, $this->repo()->all()->count());
    }
}
