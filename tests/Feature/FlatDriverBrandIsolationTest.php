<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\BrandContext\Facades\BrandContext;
use Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface;
use Goldnead\WebhookManager\Storage\FileStore;
use Goldnead\WebhookManager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Hard brand isolation for the **flat-file** driver.
 *
 * `BrandIsolationTest` proves the eloquent driver scopes correctly. This is the
 * same promise for the other driver, and it did not hold: `FileStore` was a
 * singleton bound to one path and nothing under `Repositories/FlatFile` read or
 * wrote a brand, so `content/webhooks/` held one undifferentiated set and every
 * brand read every brand's hooks.
 *
 * That is worse here than in a CRM. A webhook config carries a destination URL
 * and its credentials, so a leak is not "brand B sees brand A's data" — it is
 * "brand B can read the token brand A authenticates with", and firing a hook
 * from the wrong brand posts one tenant's payload to another tenant's endpoint.
 *
 * Brands now live in the path, matching statamic-marketing and statamic-leadhub.
 */
class FlatDriverBrandIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().'/wm-brand-flat-'.uniqid();
        config()->set('webhook-manager.storage.driver', 'flat');
        config()->set('webhook-manager.storage.flat.path', $this->path);
        config()->set('brand-context.multi_brand', true);
        config()->set('brand-context.license_check', null);

        $this->app->forgetInstance(FileStore::class);
        app('brand-context')->forget();
    }

    protected function tearDown(): void
    {
        if (isset($this->path) && File::isDirectory($this->path)) {
            File::deleteDirectory($this->path);
        }

        parent::tearDown();
    }

    private function makeBrand(string $handle): int
    {
        return (int) DB::table('brands')->insertGetId([
            'handle' => $handle,
            'name' => ucfirst($handle),
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function repo(): OutboundWebhookRepositoryInterface
    {
        return $this->app->make(OutboundWebhookRepositoryInterface::class);
    }

    private function createHook(string $name, string $url): void
    {
        $this->repo()->create([
            'name' => $name,
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'url' => $url,
            'method' => 'POST',
            'auth_type' => 'none',
            'payload_type' => 'template',
        ]);
    }

    public function test_one_brand_does_not_read_another_brands_hooks(): void
    {
        $a = $this->makeBrand('wm-a');
        $b = $this->makeBrand('wm-b');

        BrandContext::runFor($a, fn () => $this->createHook('A hook', 'https://a.example/hook'));
        BrandContext::runFor($b, fn () => $this->createHook('B hook', 'https://b.example/hook'));

        $namesIn = fn (int $brand) => BrandContext::runFor(
            $brand,
            fn () => collect($this->repo()->all())->pluck('name')->sort()->values()->all(),
        );

        $this->assertSame(['A hook'], $namesIn($a));
        $this->assertSame(['B hook'], $namesIn($b));
    }

    public function test_each_brand_gets_its_own_directory(): void
    {
        $a = $this->makeBrand('dir-a');
        $b = $this->makeBrand('dir-b');

        BrandContext::runFor($a, fn () => $this->createHook('A', 'https://a.example/x'));
        BrandContext::runFor($b, fn () => $this->createHook('B', 'https://b.example/x'));

        // Structural isolation: visible in `ls`, not hidden behind a key that a
        // reader has to remember to filter on.
        $this->assertNotEmpty(File::glob($this->path.'/dir-a/outbound/*.yaml'));
        $this->assertNotEmpty(File::glob($this->path.'/dir-b/outbound/*.yaml'));
    }

    public function test_a_secret_written_by_one_brand_is_not_readable_by_another(): void
    {
        $a = $this->makeBrand('sec-a');
        $b = $this->makeBrand('sec-b');

        BrandContext::runFor($a, function () {
            $this->repo()->create([
                'name' => 'Signed',
                'enabled' => true,
                'trigger_type' => 'entry.published',
                'url' => 'https://a.example/hook',
                'method' => 'POST',
                'auth_type' => 'bearer',
                'auth_config' => ['token' => 'super-secret-token'],
                'payload_type' => 'template',
            ]);
        });

        $serialisedForB = BrandContext::runFor(
            $b,
            fn () => json_encode(collect($this->repo()->all())->toArray()),
        );

        // The point of the whole exercise: a credential is not "data one tenant
        // can see", it is a credential.
        $this->assertStringNotContainsString('super-secret-token', (string) $serialisedForB);
    }

    public function test_it_reads_nothing_when_no_brand_is_current(): void
    {
        $a = $this->makeBrand('closed-a');
        BrandContext::runFor($a, fn () => $this->createHook('Hidden', 'https://a.example/hook'));

        app('brand-context')->forget();

        // Fail closed, matching the eloquent driver's global scope. A queue
        // worker must not see every brand just because it has no session.
        $this->assertSame([], collect($this->repo()->all())->pluck('name')->all());
    }

    public function test_the_pre_brand_layout_belongs_to_the_default_brand_only(): void
    {
        // A file written before the flat driver knew about brands.
        File::ensureDirectoryExists($this->path.'/outbound');
        File::put($this->path.'/outbound/legacy.yaml', implode("\n", [
            // `all()` drops records without an id, so the fixture needs one to
            // be a realistic pre-brand file rather than a silently ignored one.
            'id: 4711',
            'uuid: 3f1c0d64-6f4e-4a2e-9a51-6b0a1e6d2c88',
            'handle: legacy',
            'name: Legacy hook',
            'enabled: true',
            'trigger_type: entry.published',
            'url: https://legacy.example/hook',
            'method: POST',
            'auth_type: none',
            'payload_type: template',
        ])."\n");

        $other = $this->makeBrand('newcomer');
        $default = BrandContext::default()->id;

        $namesIn = fn (int $brand) => BrandContext::runFor(
            $brand,
            fn () => collect($this->repo()->all())->pluck('name')->all(),
        );

        // The default brand inherits it — an install that flips the flag must
        // not lose its hooks.
        $this->assertContains('Legacy hook', $namesIn($default));

        // And no other brand ever does. Those files predate brands.
        $this->assertNotContains('Legacy hook', $namesIn($other));
    }

    public function test_single_brand_keeps_the_pre_brand_layout(): void
    {
        config()->set('brand-context.multi_brand', false);
        app('brand-context')->forget();
        $this->app->forgetInstance(FileStore::class);

        $this->createHook('Solo', 'https://solo.example/hook');

        // No directory appears. An install that never enables multi-brand never
        // learns this feature exists.
        $this->assertNotEmpty(File::glob($this->path.'/outbound/*.yaml'));
    }
}
