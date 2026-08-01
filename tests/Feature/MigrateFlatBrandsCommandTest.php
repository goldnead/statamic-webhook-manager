<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\BrandContext\Facades\BrandContext;
use Goldnead\WebhookManager\Console\Commands\MigrateFlatBrandsCommand;
use Goldnead\WebhookManager\Storage\FileStore;
use Goldnead\WebhookManager\Tests\TestCase;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * `webhook-manager:migrate-flat-brands`.
 *
 * Moving files is the one operation a re-run cannot repair, so what is asserted
 * here is the safety: it only moves, it never overwrites, and a second run does
 * nothing.
 */
class MigrateFlatBrandsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().'/wm-migrate-'.uniqid();
        config()->set('webhook-manager.storage.driver', 'flat');
        config()->set('webhook-manager.storage.flat.path', $this->path);
        config()->set('brand-context.multi_brand', true);
        config()->set('brand-context.license_check', null);

        $this->app->forgetInstance(FileStore::class);
        app('brand-context')->forget();

        // Statamic registers addon commands from AddonServiceProvider::boot(),
        // which this testbench does not run — TestCase calls bootAddon()
        // directly instead. A real console install picks the command up twice
        // over: from $commands and from the Console/Commands autoload. Register
        // it here so the test exercises the command rather than the harness.
        $this->app[Kernel::class]
            ->registerCommand($this->app->make(MigrateFlatBrandsCommand::class));
    }

    protected function tearDown(): void
    {
        if (isset($this->path) && File::isDirectory($this->path)) {
            File::deleteDirectory($this->path);
        }

        parent::tearDown();
    }

    private function legacyHook(string $handle = 'legacy'): void
    {
        File::ensureDirectoryExists($this->path.'/outbound');
        File::put($this->path.'/outbound/'.$handle.'.yaml', "id: 1\nhandle: {$handle}\nname: Legacy\n");
    }

    public function test_it_moves_the_pre_brand_layout_into_the_default_brand(): void
    {
        $this->legacyHook();
        File::ensureDirectoryExists($this->path.'/templates');
        File::put($this->path.'/templates/envelope.yaml', "id: 2\nhandle: envelope\nname: Envelope\n");

        $default = BrandContext::default()->handle;

        $this->artisan('webhook-manager:migrate-flat-brands')->assertExitCode(0);

        $this->assertFileExists($this->path.'/'.$default.'/outbound/legacy.yaml');
        $this->assertFileExists($this->path.'/'.$default.'/templates/envelope.yaml');

        // Moved, not copied. Two files for one handle is exactly what the
        // store's write path works to avoid.
        $this->assertFileDoesNotExist($this->path.'/outbound/legacy.yaml');
        $this->assertFileDoesNotExist($this->path.'/templates/envelope.yaml');
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $this->legacyHook();

        $this->artisan('webhook-manager:migrate-flat-brands --dry-run')->assertExitCode(0);

        $this->assertFileExists($this->path.'/outbound/legacy.yaml');
    }

    public function test_a_second_run_is_a_no_op(): void
    {
        $this->legacyHook();

        $this->artisan('webhook-manager:migrate-flat-brands')->assertExitCode(0);
        $this->artisan('webhook-manager:migrate-flat-brands')
            ->expectsOutputToContain('Nothing to move')
            ->assertExitCode(0);
    }

    public function test_it_never_overwrites_a_directory_already_in_the_target(): void
    {
        $default = BrandContext::default()->handle;

        $this->legacyHook();
        File::ensureDirectoryExists($this->path.'/'.$default.'/outbound');
        File::put($this->path.'/'.$default.'/outbound/keep.yaml', "id: 9\nhandle: keep\nname: Keep me\n");

        $this->artisan('webhook-manager:migrate-flat-brands')->assertExitCode(0);

        // A target that already exists means a finished migration or a genuine
        // conflict, and neither is resolved by clobbering.
        $this->assertStringContainsString('Keep me', File::get($this->path.'/'.$default.'/outbound/keep.yaml'));
    }

    public function test_it_can_target_a_named_brand(): void
    {
        $this->legacyHook();

        DB::table('brands')->insert([
            'handle' => 'target', 'name' => 'Target', 'is_default' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('webhook-manager:migrate-flat-brands --brand=target')->assertExitCode(0);

        $this->assertFileExists($this->path.'/target/outbound/legacy.yaml');
    }

    public function test_it_rejects_an_unknown_brand(): void
    {
        $this->artisan('webhook-manager:migrate-flat-brands --brand=nope')
            ->expectsOutputToContain('No brand [nope]')
            ->assertExitCode(1);
    }

    public function test_it_does_nothing_on_a_single_brand_install(): void
    {
        config()->set('brand-context.multi_brand', false);
        app('brand-context')->forget();

        $this->legacyHook();

        $this->artisan('webhook-manager:migrate-flat-brands')
            ->expectsOutputToContain('Single-brand install')
            ->assertExitCode(0);

        // The pre-brand layout IS the correct layout there.
        $this->assertFileExists($this->path.'/outbound/legacy.yaml');
    }

    public function test_it_does_nothing_on_the_eloquent_driver(): void
    {
        config()->set('webhook-manager.storage.driver', 'eloquent');

        $this->artisan('webhook-manager:migrate-flat-brands')
            ->expectsOutputToContain('flat driver is not active')
            ->assertExitCode(0);
    }
}
