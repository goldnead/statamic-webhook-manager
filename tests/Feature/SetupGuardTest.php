<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Storage\StorageDriverManager;
use Goldnead\WebhookManager\Tests\CpTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * An addon that is installed but never migrated answers HTTP 500 on every one
 * of its CP screens: the nav item is there, the route resolves, and the first
 * query throws `no such table: webhook_outbounds` while the page is built.
 *
 * These tests reproduce exactly that database — everything present except this
 * addon's own tables — and hold each guarded page to two things: an empty state
 * the reader can act on, and a line in the log saying why. The second half is
 * the point. Without it the addon would have traded a visible 500 for a silent
 * nothing, and an install that looks finished and never works is worse than one
 * that crashes.
 */
class SetupGuardTest extends CpTestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The detail routes read the config tables when the route file loads,
        // which happens at boot, before setUp(). A driver file left behind by an
        // earlier test (the flat-driver case writes one) would empty that list
        // for every test after it, so it is removed before the app boots.
        @unlink($app->storagePath('webhook-manager/storage.json'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        // The chosen storage driver is a file under storage/, not a database
        // row, so it survives from one test into the next in the same process.
        // Cleared so every case here starts on the packaged `eloquent` — the
        // flat driver reads YAML and would need no table at all.
        @unlink($this->app->make(StorageDriverManager::class)->settingsPath());
    }

    /**
     * Every guarded index route, with the page name Setup::guard() logs.
     *
     * @return array<string, array{0:string, 1:string}>
     */
    public static function guardedRoutes(): array
    {
        return [
            'overview' => ['webhook-manager.overview', 'webhook_deliveries'],
            'outbound' => ['webhook-manager.outbound.index', 'webhook_outbounds'],
            'inbound' => ['webhook-manager.inbound.index', 'webhook_inbounds'],
            'rules' => ['webhook-manager.rules.index', 'webhook_rules'],
            // Deliveries, not outbounds: the Insights screen is an aggregate
            // over the delivery history, which stays in the database under
            // every driver, so that is the table its guard must always name.
            'insights' => ['webhook-manager.insights', 'webhook_deliveries'],
            'deliveries' => ['webhook-manager.deliveries.index', 'webhook_deliveries'],
            'logs' => ['webhook-manager.logs.index', 'webhook_logs'],
            'templates' => ['webhook-manager.templates.index', 'webhook_templates'],
            'debug' => ['webhook-manager.debug', 'webhook_outbounds'],
        ];
    }

    /**
     * The listing components each route renders once its tables are there.
     *
     * @return array<string, array{0:string, 1:string}>
     */
    public static function migratedRoutes(): array
    {
        return [
            'overview' => ['webhook-manager.overview', 'webhook-manager::Overview/Index'],
            'outbound' => ['webhook-manager.outbound.index', 'webhook-manager::Outbound/Index'],
            'inbound' => ['webhook-manager.inbound.index', 'webhook-manager::Inbound/Index'],
            'rules' => ['webhook-manager.rules.index', 'webhook-manager::Rules/Index'],
            'insights' => ['webhook-manager.insights', 'webhook-manager::Insights/Index'],
            'deliveries' => ['webhook-manager.deliveries.index', 'webhook-manager::Deliveries/Index'],
            'logs' => ['webhook-manager.logs.index', 'webhook-manager::Logs/Index'],
            'templates' => ['webhook-manager.templates.index', 'webhook-manager::Templates/Index'],
            'debug' => ['webhook-manager.debug', 'webhook-manager::Debug/Index'],
        ];
    }

    /**
     * Run something against a database on which nothing has ever been migrated.
     *
     * An empty second connection rather than `Schema::drop()` on the real one,
     * and the reason is worth writing down. MySQL commits DDL implicitly, so a
     * dropped table is not something the RefreshDatabase transaction can put
     * back; testbench then rolls its registered migrations back at the end of
     * the run and meets a `down()` that cannot alter a table which is no longer
     * there — `Table 'webhook_manager_test.webhook_outbounds' doesn't exist`,
     * raised in teardown, the worst place to go looking for it. The MySQL leg
     * had been red with exactly that since the guard was first written.
     *
     * An empty database is also the more faithful picture of the install this
     * whole feature exists for: the addon is there, the tables never were. And
     * it costs no DDL at all, so it behaves the same on both engines.
     */
    private function withUnmigratedDatabase(callable $work): mixed
    {
        config()->set('database.connections.unmigrated', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        $previous = (string) config('database.default');

        config()->set('database.default', 'unmigrated');
        DB::setDefaultConnection('unmigrated');

        try {
            return $work();
        } finally {
            config()->set('database.default', $previous);
            DB::setDefaultConnection($previous);
            DB::purge('unmigrated');
        }
    }

    #[DataProvider('guardedRoutes')]
    public function test_the_page_answers_200_when_its_tables_are_missing(string $route, string $table): void
    {
        $this->withUnmigratedDatabase(fn () => $this->actingAs($this->superUser())
            ->withHeaders($this->inertiaHeaders())
            ->get(cp_route($route))
            ->assertOk("$route stirbt ohne $table statt den Leerzustand zu zeigen"));
    }

    #[DataProvider('guardedRoutes')]
    public function test_the_page_renders_the_setup_screen_and_names_the_missing_tables(string $route, string $table): void
    {
        $page = $this->withUnmigratedDatabase(fn () => $this->actingAs($this->superUser())
            ->withHeaders($this->inertiaHeaders())
            ->get(cp_route($route))
            ->assertOk()
            ->json());

        $this->assertSame('webhook-manager::SetupRequired', $page['component'], "$route rendert nicht den Leerzustand");
        $this->assertContains($table, $page['props']['tables'], "$route nennt $table nicht als fehlend");
        $this->assertNotEmpty($page['props']['heading']);
        $this->assertNotEmpty($page['props']['description']);
        $this->assertNotEmpty($page['props']['title']);
    }

    /**
     * The most important case here. The guard exists to make the page
     * readable, not to make the failure quiet: if this goes red, the addon
     * shows an empty state and tells nobody, anywhere, that the install is
     * unfinished.
     */
    #[DataProvider('guardedRoutes')]
    public function test_the_reason_reaches_the_log(string $route, string $table): void
    {
        Log::spy();

        $this->withUnmigratedDatabase(function () use ($route, $table) {
            $this->assertFalse(Schema::hasTable($table), "$table sollte für diesen Fall weg sein");

            $this->actingAs($this->superUser())
                ->withHeaders($this->inertiaHeaders())
                ->get(cp_route($route))
                ->assertOk();
        });

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message) => str_contains($message, 'statamic-webhook-manager')
                && str_contains($message, 'php artisan migrate'))
            ->once();
    }

    #[DataProvider('migratedRoutes')]
    public function test_a_migrated_install_still_renders_the_normal_page(string $route, string $component): void
    {
        $page = $this->actingAs($this->superUser())
            ->withHeaders($this->inertiaHeaders())
            ->get(cp_route($route))
            ->assertOk()
            ->json();

        $this->assertSame($component, $page['component']);
    }

    /**
     * The flat-file driver keeps outbound webhooks, endpoints, rules and
     * templates in YAML, so those four tables are never queried. Demanding
     * them would send a working installation to the setup screen — which is
     * why the config tables go through Setup::configTables() and the
     * database-only ones (deliveries, logs) do not.
     */
    #[Test]
    public function the_flat_driver_needs_no_configuration_tables(): void
    {
        $this->app->make(StorageDriverManager::class)->setDriver('flat');

        $page = $this->withUnmigratedDatabase(fn () => $this->actingAs($this->superUser())
            ->withHeaders($this->inertiaHeaders())
            ->get(cp_route('webhook-manager.outbound.index'))
            ->assertOk()
            ->json());

        $this->assertSame('webhook-manager::Outbound/Index', $page['component']);
    }

    /**
     * The detail pages. Their first query is the route-model binding, which
     * runs before the controller method, so the guard in `index()` never sees
     * it: a missing table is an HTTP 500 there, not the setup screen.
     *
     * @return array<string, array{0:string}>
     */
    public static function detailRoutes(): array
    {
        return [
            'outbound edit' => ['webhook-manager.outbound.edit'],
            'inbound edit' => ['webhook-manager.inbound.edit'],
            'rules edit' => ['webhook-manager.rules.edit'],
            'templates edit' => ['webhook-manager.templates.edit'],
            'deliveries show' => ['webhook-manager.deliveries.show'],
        ];
    }

    #[DataProvider('detailRoutes')]
    public function test_a_detail_page_shows_the_setup_screen_when_its_tables_are_missing(string $route): void
    {
        $page = $this->withUnmigratedDatabase(fn () => $this->actingAs($this->superUser())
            ->withHeaders($this->inertiaHeaders())
            ->get(cp_route($route, 1))
            ->assertOk()
            ->json());

        $this->assertSame('webhook-manager::SetupRequired', $page['component'], "$route rendert nicht den Leerzustand");
    }

    // No guest test here: CpTestCase registers neither the `statamic.cp` group nor Statamic's login check, so a
    // guest is not turned away in this suite whatever the route carries. That the setup screen stays behind the
    // login is the job of GuardSetup in statamic-brand-context and is tested there.

    #[DataProvider('detailRoutes')]
    public function test_a_migrated_install_still_404s_an_unknown_id(string $route): void
    {
        $this->actingAs($this->superUser())
            ->withHeaders($this->inertiaHeaders())
            ->get(cp_route($route, 999))
            ->assertNotFound();
    }
}
