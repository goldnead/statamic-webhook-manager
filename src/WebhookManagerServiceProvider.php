<?php

namespace Goldnead\WebhookManager;

use Goldnead\WebhookManager\Actions\Cp\SendWebhook;
use Goldnead\WebhookManager\Auth\Support\ReplayProtectionService;
use Goldnead\WebhookManager\Console\Commands\DispatchDueRetriesCommand;
use Goldnead\WebhookManager\Console\Commands\InspectWebhookHealthCommand;
use Goldnead\WebhookManager\Console\Commands\MigrateFlatBrandsCommand;
use Goldnead\WebhookManager\Console\Commands\PruneWebhookDataCommand;
use Goldnead\WebhookManager\Console\Commands\ReplayFailedDeliveriesCommand;
use Goldnead\WebhookManager\Console\Commands\SeedWebhookExamplesCommand;
use Goldnead\WebhookManager\Console\Commands\StorageMigrateCommand;
use Goldnead\WebhookManager\Contracts\Repositories\InboundEndpointRepositoryInterface;
use Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface;
use Goldnead\WebhookManager\Contracts\Repositories\RuleRepositoryInterface;
use Goldnead\WebhookManager\Contracts\Repositories\TemplateRepositoryInterface;
use Goldnead\WebhookManager\Events\DeliveryFailedTerminally;
use Goldnead\WebhookManager\Events\TriggerDetected;
use Goldnead\WebhookManager\Http\Middleware\ResolveInboundBrand;
use Goldnead\WebhookManager\Listeners\DispatchTriggerListener;
use Goldnead\WebhookManager\Listeners\HandleAssetSavedListener;
use Goldnead\WebhookManager\Listeners\HandleEntryDeletedListener;
use Goldnead\WebhookManager\Listeners\HandleEntryPublishedListener;
use Goldnead\WebhookManager\Listeners\HandleEntrySavedListener;
use Goldnead\WebhookManager\Listeners\HandleEntryUnpublishedListener;
use Goldnead\WebhookManager\Listeners\HandleFormSubmittedListener;
use Goldnead\WebhookManager\Listeners\HandleUserSavedListener;
use Goldnead\WebhookManager\Listeners\SendFailureAlertListener;
use Goldnead\WebhookManager\Registries\ActionRegistry;
use Goldnead\WebhookManager\Registries\AuthSchemeRegistry;
use Goldnead\WebhookManager\Registries\ConditionRegistry;
use Goldnead\WebhookManager\Registries\InboundActionHandlerRegistry;
use Goldnead\WebhookManager\Registries\PresetRegistry;
use Goldnead\WebhookManager\Registries\SuccessEvaluatorRegistry;
use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Goldnead\WebhookManager\Registries\VariableResolverRegistry;
use Goldnead\WebhookManager\Repositories\Eloquent\EloquentInboundEndpointRepository;
use Goldnead\WebhookManager\Repositories\Eloquent\EloquentOutboundWebhookRepository;
use Goldnead\WebhookManager\Repositories\Eloquent\EloquentRuleRepository;
use Goldnead\WebhookManager\Repositories\Eloquent\EloquentTemplateRepository;
use Goldnead\WebhookManager\Repositories\FlatFile\FlatFileInboundEndpointRepository;
use Goldnead\WebhookManager\Repositories\FlatFile\FlatFileOutboundWebhookRepository;
use Goldnead\WebhookManager\Repositories\FlatFile\FlatFileRuleRepository;
use Goldnead\WebhookManager\Repositories\FlatFile\FlatFileTemplateRepository;
use Goldnead\WebhookManager\Storage\BrandSegments;
use Goldnead\WebhookManager\Storage\FileStore;
use Goldnead\WebhookManager\Storage\ModelHydrator;
use Goldnead\WebhookManager\Storage\StorageDriverManager;
use Goldnead\WebhookManager\Storage\StorageMigrator;
use Goldnead\WebhookManager\Support\Settings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;

/**
 * Service provider for the Statamic Webhook Manager addon.
 *
 * Bootstraps configuration, migrations, routes, navigation, permissions,
 * the central registries that power triggers/auth schemes/variable
 * resolvers/success evaluators, and ships the Vite-built Vue/Inertia
 * pages into the Statamic 6 Control Panel.
 */
class WebhookManagerServiceProvider extends AddonServiceProvider
{
    /**
     * Canonical public prefix of the inbound endpoint.
     *
     * Up to and including v1.7.3 this was Statamic's `!/` utility prefix. No
     * inbound delivery was ever recorded on any environment under that URL:
     * `!` is not a character anyone types into a provider's webhook field, and
     * every human-facing default in this addon already spelled it without one.
     */
    public const DEFAULT_INBOUND_PREFIX = 'webhooks/inbound';

    /** The pre-v1.8.0 prefix, kept routable so nothing silently breaks. */
    public const LEGACY_INBOUND_PREFIX = '!/webhooks/inbound';

    /**
     * Everything the inbound endpoint needs and nothing else — which, as of
     * 2.1.0, is nothing beyond the brand resolver `inboundMiddleware()` puts in
     * front. Explicitly absent: the whole `web` group.
     *
     * `SubstituteBindings` used to sit here "so that a future bound route
     * parameter resolves". No inbound route has a bound parameter: `{brand}`
     * and `{handle}` are generic names, deliberately unbound, and
     * `RouteParameterCollisionTest` keeps them that way. So it resolved nothing
     * — while doing one thing that was not free. A `Route::bind('brand')`
     * registered by the host app or any sibling addon applies to every route
     * with that parameter name, including this one, and a binding that aborts
     * when it resolves nothing (which is what a model binding does) killed the
     * delivery here. Measured, not reasoned: with it in the stack a foreign
     * binding turned a correctly signed delivery into a 404.
     *
     * An install that published `config/webhook-manager.php` before 2.1.0 keeps
     * `SubstituteBindings` in its own copy of this list, and with it that
     * exposure. Removing it there is one line and worth doing.
     */
    public const DEFAULT_INBOUND_MIDDLEWARE = [];

    /** Guards against bootAddon() running twice on the same instance. */
    protected bool $inboundRoutesBooted = false;

    /**
     * Vite configuration for the addon's CP bundle. Statamic 6 uses this
     * to load the addon's compiled JS/CSS into the Inertia SPA.
     */
    protected $vite = [
        'hotFile' => __DIR__.'/../resources/dist/hot',
        'publicDirectory' => 'resources/dist',
        'input' => [
            'resources/js/cp.js',
            'resources/css/cp.css',
        ],
    ];

    /**
     * Event listeners. Statamic event class FQCNs are referenced as
     * strings so the package boots even when running unit tests against
     * a stripped-down Laravel without full Statamic boot.
     */
    protected $listen = [
        'Statamic\Events\EntrySaved' => [
            HandleEntrySavedListener::class,
            HandleEntryPublishedListener::class,
            HandleEntryUnpublishedListener::class,
        ],
        'Statamic\Events\EntryDeleted' => [
            HandleEntryDeletedListener::class,
        ],
        'Statamic\Events\FormSubmitted' => [
            HandleFormSubmittedListener::class,
        ],
        'Statamic\Events\UserSaved' => [
            HandleUserSavedListener::class,
        ],
        'Statamic\Events\AssetSaved' => [
            HandleAssetSavedListener::class,
        ],
        TriggerDetected::class => [
            DispatchTriggerListener::class,
        ],
        DeliveryFailedTerminally::class => [
            SendFailureAlertListener::class,
        ],
    ];

    /**
     * Only CP routes go through Statamic's route hook.
     *
     * `routes/inbound.php` deliberately does NOT appear here. Statamic drops
     * every route registered under the `web` key into the application's `web`
     * middleware group, which is the wrong stack for a machine-to-machine
     * endpoint — see the header comment in routes/inbound.php. It is loaded by
     * bootInboundRoutes() with an explicit middleware stack instead.
     */
    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
    ];

    protected $commands = [
        DispatchDueRetriesCommand::class,
        PruneWebhookDataCommand::class,
        ReplayFailedDeliveriesCommand::class,
        InspectWebhookHealthCommand::class,
        SeedWebhookExamplesCommand::class,
        StorageMigrateCommand::class,
        MigrateFlatBrandsCommand::class,
    ];

    /**
     * Native Statamic CP actions. Auto-discovery only scans the top level of
     * src/Actions/ (which holds the internal rule actions), so the entry
     * action under Actions/Cp/ is registered explicitly here.
     */
    protected $actions = [
        SendWebhook::class,
    ];

    public function bootAddon(): void
    {
        $this->bootWebhookConfig();
        $this->bootSettingsOverrides();
        $this->bootMigrations();
        $this->bootBindings();
        $this->bootWebhookPublishables();
        $this->bootPermissions();
        $this->bootNavigation();
        $this->bootRegistries();
        $this->bootRouteBindings();
        $this->bootInboundRoutes();
    }

    /**
     * Put the retry dispatcher on the scheduler.
     *
     * Retries were planned and never run: `DeliveryEngine` wrote
     * `next_retry_at`, the CP showed "next retry in 30 seconds" and
     * `DeliveryRepository::readyForRetry()` — the query written to find those
     * rows — had no caller anywhere in the package. A scheduled command is the
     * mechanism `ProcessOutboundDeliveryJob`'s own docblock already assumed
     * existed.
     *
     * Registered here rather than left to the host app's `routes/console.php`,
     * because a retry that only happens when the site owner remembers to wire
     * it up is not a retry. `withoutOverlapping()` bounds a slow run; the
     * command additionally claims each row before working on it, so even an
     * overlapping run cannot double-send.
     *
     * Set `webhook-manager.retry.schedule` to false to take it off the
     * scheduler and drive `webhook-manager:dispatch-retries` yourself.
     */
    public function schedule(Schedule $schedule): void
    {
        if (! config('webhook-manager.retry.schedule', true)) {
            return;
        }

        $schedule->command(DispatchDueRetriesCommand::class)
            ->everyMinute()
            ->withoutOverlapping();
    }

    /**
     * The canonical, config-resolved prefix the inbound endpoint is served
     * under. The CP renders endpoint URLs from this same value, so the URL an
     * operator copies out of the CP is the URL that is actually routed.
     */
    public static function inboundRoutePrefix(): string
    {
        return trim((string) config('webhook-manager.inbound.route_prefix', self::DEFAULT_INBOUND_PREFIX), '/');
    }

    /**
     * Prefixes kept routable for senders configured against an older release.
     */
    public static function inboundLegacyRoutePrefixes(): array
    {
        return array_values(array_filter(array_map(
            fn ($p) => trim((string) $p, '/'),
            (array) config('webhook-manager.inbound.legacy_route_prefixes', [self::LEGACY_INBOUND_PREFIX]),
        )));
    }

    /**
     * The public path of one inbound endpoint, brand segment included.
     *
     * One place builds this string. The CP prints what this returns, the README
     * documents it, and `routes/inbound.php` matches it — the previous split,
     * where the CP assembled a URL of its own from `path` while the router
     * matched on `handle`, is how an operator ended up copying a URL that 404s.
     *
     * The brand segment is always present, on single-brand installs too: one
     * URL shape everywhere is worth more than four characters saved, and
     * ResolveInboundBrand accepts the default brand's handle in single-brand
     * mode precisely so this holds.
     */
    public const INBOUND_SEGMENT_PATTERN = '[a-z0-9_-]+';

    public static function inboundPathIsRoutable(?string $brandHandle = null): bool
    {
        $brandHandle ??= app('brand-context')->current()->handle;

        return (bool) preg_match('/^'.self::INBOUND_SEGMENT_PATTERN.'$/', $brandHandle);
    }

    public static function inboundPath(string $handle, ?string $brandHandle = null): string
    {
        $brandHandle ??= app('brand-context')->current()->handle;

        $prefix = '/'.static::inboundRoutePrefix();

        // A brand handle is not validated against anything on the way in — the
        // model is unguarded and the column carries only a unique index — so a
        // brand called `Chor.de` is possible, and the router (`->where('brand',
        // …)`) cannot match it.
        //
        // Dropping the segment in that case was the first attempt and it was
        // worse than the problem: `{prefix}/{handle}` is a perfectly routable
        // URL that resolves to the **default** brand, so the operator got no
        // sign of trouble at all — just a URL quietly pointing at the wrong
        // tenant. A URL carrying `Chor.de` is at least visibly the handle of
        // the brand being looked at, and it fails loudly at the first delivery.
        //
        // `inboundPathIsRoutable()` is how a caller finds out before then.
        return $prefix.'/'.$brandHandle.'/'.$handle;
    }

    /**
     * The complete middleware stack the inbound endpoint runs.
     *
     * Deliberately not the `web` group. A webhook sender has no session and no
     * CSRF token, so `web` answers every real delivery with 419 before auth is
     * ever consulted — and even with CSRF excluded it would still start a
     * session, encrypt cookies and run the host app's Inertia/redirect
     * middleware on a machine endpoint.
     *
     * The endpoint is not left unprotected by this: authentication is the
     * endpoint's own configured verifier, enforced in InboundRequestProcessor
     * before parsing, mapping or action dispatch.
     *
     * `ResolveInboundBrand` is prepended and is deliberately **not** part of
     * the configurable list. Without it a multi-brand install answers every
     * delivery with 404 (the brand-scoped lookup fails closed), so it is not a
     * stack an operator can meaningfully choose to drop — and the installs that
     * would silently drop it are exactly the ones that published this config
     * file before the middleware existed and would never notice it missing.
     */
    public static function inboundMiddleware(): array
    {
        $configured = array_values((array) config(
            'webhook-manager.inbound.middleware',
            self::DEFAULT_INBOUND_MIDDLEWARE,
        ));

        return array_values(array_unique(array_merge(
            [ResolveInboundBrand::class],
            $configured,
        )));
    }

    /**
     * Register the inbound endpoint outside Statamic's `web` route hook.
     *
     * Timing matters: bootAddon() runs from `Statamic::booted()`, which
     * Statamic fires from its `$app->booted()` callback *before* it loads its
     * own route files. These routes are therefore matched ahead of Statamic's
     * front-end catch-all `Route::any('/{segments?}')`, exactly as they were
     * when Statamic registered them via `additionalWebRoutes()`.
     */
    protected function bootInboundRoutes(): void
    {
        if ($this->inboundRoutesBooted) {
            return;
        }

        $this->inboundRoutesBooted = true;

        $prefix = static::inboundRoutePrefix();
        $middleware = static::inboundMiddleware();
        $legacyPrefixes = static::inboundLegacyRoutePrefixes();

        require __DIR__.'/../routes/inbound.php';
    }

    /**
     * The route parameters this addon resolves through `Route::bind()`, mapped
     * to the repository contract that resolves them.
     *
     * A `Route::bind()` is registered on the router, not on the package: it
     * applies to every route with that parameter name in every addon installed
     * alongside. Binding a generic name therefore reaches into other people's
     * routes, and the route that loses does not fail loudly — it resolves a
     * foreign id against a repository here that has never heard of it and
     * answers 404. That is how leadhub 1.8.0's `/scoring/{rule}` lost its edit
     * and delete on the hub, through a release, without an error anywhere.
     *
     * Hence the rule this addon follows, and the reason these names look the
     * way they do:
     *
     *   A bound parameter name must be owned by the addon that binds it —
     *   specific enough that no sibling would reach for it by accident.
     *
     * Here that means the `webhook` prefix plus a capital: `webhookOutbound`,
     * `webhookInbound`, `webhookRule`, `webhookTemplate`. Nothing generic is
     * bound, so nothing generic is taken away from anyone.
     *
     * The names are only ever the URL *placeholders*. The URLs themselves are
     * unchanged, and so are the payload keys the Vue pages read.
     *
     * `RouteParameterCollisionTest` enforces the rule against this array.
     *
     * @return array<string, class-string> route parameter => repository contract
     */
    public static function routeModelBindings(): array
    {
        return [
            'webhookOutbound' => OutboundWebhookRepositoryInterface::class,
            'webhookInbound' => InboundEndpointRepositoryInterface::class,
            'webhookRule' => RuleRepositoryInterface::class,
            'webhookTemplate' => TemplateRepositoryInterface::class,
        ];
    }

    /**
     * Resolve the config-entity route parameters through the repository
     * layer instead of Eloquent implicit binding, so CP routes work under
     * both the database and the flat-file storage driver. Delivery/log
     * params remain database-bound (those tables are always Eloquent).
     */
    protected function bootRouteBindings(): void
    {
        foreach (static::routeModelBindings() as $param => $contract) {
            Route::bind($param, function ($value) use ($contract) {
                $model = $this->app->make($contract)->find($value);
                abort_if($model === null, 404);

                return $model;
            });
        }
    }

    protected function bootWebhookConfig(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webhook-manager.php', 'webhook-manager');

        $this->publishes([
            __DIR__.'/../config/webhook-manager.php' => config_path('webhook-manager.php'),
        ], 'webhook-manager-config');
    }

    /**
     * Put the settings changed in the Control Panel onto the live config.
     *
     * Directly after the config file is merged and before anything reads it:
     * the feature toggles gate navigation and inbound route registration, both
     * of which happen further down this same method, and a queue worker booting
     * this addon has to see the operator's retry and HTTP values without any
     * Control-Panel middleware having run.
     *
     * Not everything can be reached from here. `bootSchedule()` runs *before*
     * `bootAddon()` in Statamic's AddonServiceProvider, which is why
     * `retry.schedule` is deliberately not an editable setting.
     */
    protected function bootSettingsOverrides(): void
    {
        $this->app->singleton(Settings::class);

        $this->app->make(Settings::class)->apply();
    }

    protected function bootMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Singleton bindings for the central registries plus a string alias
     * used by the WebhookManager facade.
     */
    protected function bootBindings(): void
    {
        $this->app->singleton(TriggerRegistry::class);
        $this->app->singleton(AuthSchemeRegistry::class);
        $this->app->singleton(ConditionRegistry::class);
        $this->app->singleton(ActionRegistry::class);
        $this->app->singleton(VariableResolverRegistry::class);
        $this->app->singleton(SuccessEvaluatorRegistry::class);
        $this->app->singleton(InboundActionHandlerRegistry::class);
        $this->app->singleton(PresetRegistry::class);

        $this->app->singleton(ReplayProtectionService::class, function ($app) {
            return new ReplayProtectionService(
                $app['cache.store'],
                (int) config('webhook-manager.inbound.replay_protection_ttl_seconds', 600),
            );
        });

        $this->app->singleton('webhook-manager', WebhookManager::class);

        $this->bindStorageRepositories();
    }

    /**
     * Bind each config repository contract to the Eloquent or FlatFile
     * implementation for the active driver (StorageDriverManager: a
     * Control-Panel choice, else `webhook-manager.storage.driver`).
     *
     * Bindings are lazy closures, so changing the driver before the
     * repository is first resolved still takes effect. Delivery and log
     * repositories are database-only and are not part of this abstraction.
     */
    protected function bindStorageRepositories(): void
    {
        // One memo, one flush point, shared by everything that resolves a path.
        $this->app->singleton(BrandSegments::class);

        $this->app->singleton(FileStore::class, function ($app) {
            return new FileStore(
                (string) config('webhook-manager.storage.flat.path', base_path('content/webhooks')),
                $app->make(BrandSegments::class),
            );
        });

        $this->app->singleton(ModelHydrator::class);
        $this->app->singleton(StorageDriverManager::class);
        $this->app->singleton(StorageMigrator::class);

        $map = [
            OutboundWebhookRepositoryInterface::class => [
                EloquentOutboundWebhookRepository::class,
                FlatFileOutboundWebhookRepository::class,
            ],
            InboundEndpointRepositoryInterface::class => [
                EloquentInboundEndpointRepository::class,
                FlatFileInboundEndpointRepository::class,
            ],
            RuleRepositoryInterface::class => [
                EloquentRuleRepository::class,
                FlatFileRuleRepository::class,
            ],
            TemplateRepositoryInterface::class => [
                EloquentTemplateRepository::class,
                FlatFileTemplateRepository::class,
            ],
        ];

        foreach ($map as $contract => [$eloquent, $flat]) {
            $this->app->bind($contract, function ($app) use ($eloquent, $flat) {
                // The active driver comes from the StorageDriverManager, which
                // prefers a Control-Panel-persisted choice over config/env.
                return $app->make(StorageDriverManager::class)->current() === 'flat'
                    ? $app->make($flat)
                    : $app->make($eloquent);
            });
        }
    }

    protected function bootPermissions(): void
    {
        Permission::group('webhook_manager', __('webhook-manager::permissions.group'), function () {
            Permission::register('view webhooks')
                ->label(__('webhook-manager::permissions.view_webhooks'));
            Permission::register('manage outbound webhooks')
                ->label(__('webhook-manager::permissions.manage_outbound'));
            // Fires a real request at a real third-party endpoint, so it is
            // its own ability rather than a side effect of "manage". It was
            // referenced by the CP long before it was registered — an
            // unregistered ability answers `false` for everyone, super users
            // included, which is why the Test button never appeared.
            Permission::register('test outbound webhooks')
                ->label(__('webhook-manager::permissions.test_outbound'));
            Permission::register('manage inbound endpoints')
                ->label(__('webhook-manager::permissions.manage_inbound'));
            Permission::register('manage webhook rules')
                ->label(__('webhook-manager::permissions.manage_rules'));
            Permission::register('view webhook deliveries')
                ->label(__('webhook-manager::permissions.view_deliveries'));
            Permission::register('replay webhook deliveries')
                ->label(__('webhook-manager::permissions.replay_deliveries'));
            Permission::register('view sensitive payloads')
                ->label(__('webhook-manager::permissions.view_sensitive'));
            Permission::register('manage webhook settings')
                ->label(__('webhook-manager::permissions.manage_settings'));
            Permission::register('manage webhook templates')
                ->label(__('webhook-manager::permissions.manage_templates'));
            Permission::register('use webhook debug tools')
                ->label(__('webhook-manager::permissions.use_debug'));
        });
    }

    protected function bootNavigation(): void
    {
        Nav::extend(function ($nav) {
            $features = config('webhook-manager.features', []);
            $enabled = fn (string $feature) => (bool) ($features[$feature] ?? true);

            // Child items are gated by both the feature toggle and the user's
            // permission, so disabling a module in config hides its CP screens
            // from the sidebar. Overview, deliveries and logs are always shown.
            $children = [
                $nav->item(__('webhook-manager::nav.overview'))->route('webhook-manager.overview'),
            ];

            if ($enabled('outbound')) {
                $children[] = $nav->item(__('webhook-manager::nav.outbound'))->route('webhook-manager.outbound.index')->can('manage outbound webhooks');
            }
            if ($enabled('inbound')) {
                $children[] = $nav->item(__('webhook-manager::nav.inbound'))->route('webhook-manager.inbound.index')->can('manage inbound endpoints');
            }
            if ($enabled('rules')) {
                $children[] = $nav->item(__('webhook-manager::nav.rules'))->route('webhook-manager.rules.index')->can('manage webhook rules');
            }

            $children[] = $nav->item(__('webhook-manager::nav.insights'))->route('webhook-manager.insights')->can('view webhook deliveries');
            $children[] = $nav->item(__('webhook-manager::nav.deliveries'))->route('webhook-manager.deliveries.index')->can('view webhook deliveries');
            $children[] = $nav->item(__('webhook-manager::nav.logs'))->route('webhook-manager.logs.index')->can('view webhooks');

            if ($enabled('templates')) {
                $children[] = $nav->item(__('webhook-manager::nav.templates'))->route('webhook-manager.templates.index')->can('manage webhook templates');
            }

            $children[] = $nav->item(__('webhook-manager::nav.settings'))->route('webhook-manager.settings')->can('manage webhook settings');

            if ($enabled('debug_tools')) {
                $children[] = $nav->item(__('webhook-manager::nav.debug'))->route('webhook-manager.debug')->can('use webhook debug tools');
            }

            $nav->create(__('webhook-manager::nav.webhooks'))
                ->section(__('webhook-manager::nav.section'))
                ->route('webhook-manager.overview')
                ->icon('link')
                ->can('view webhooks')
                ->children($children);
        });
    }

    /**
     * Register built-in triggers, auth schemes, evaluators and variable resolvers.
     */
    protected function bootRegistries(): void
    {
        /** @var TriggerRegistry $triggers */
        $triggers = $this->app->make(TriggerRegistry::class);
        $triggers->registerDefaults();

        /** @var AuthSchemeRegistry $auth */
        $auth = $this->app->make(AuthSchemeRegistry::class);
        $auth->registerDefaults();

        /** @var VariableResolverRegistry $vars */
        $vars = $this->app->make(VariableResolverRegistry::class);
        $vars->registerDefaults();

        /** @var SuccessEvaluatorRegistry $eval */
        $eval = $this->app->make(SuccessEvaluatorRegistry::class);
        $eval->registerDefaults();

        /** @var InboundActionHandlerRegistry $inboundActions */
        $inboundActions = $this->app->make(InboundActionHandlerRegistry::class);
        $inboundActions->registerDefaults();

        /** @var ActionRegistry $actions */
        $actions = $this->app->make(ActionRegistry::class);
        $actions->registerDefaults();

        /** @var PresetRegistry $presets */
        $presets = $this->app->make(PresetRegistry::class);
        $presets->registerDefaults();

        $this->registerCustomEventTriggers();
    }

    /**
     * Wire up config-driven custom event triggers. Each entry under
     * `webhook-manager.event_triggers` turns an arbitrary event class into a
     * webhook trigger via WebhookManager::registerEventTrigger(), which
     * registers the trigger in the registry AND attaches the generic listener
     * that feeds the standard dispatch pipeline.
     */
    protected function registerCustomEventTriggers(): void
    {
        $manager = $this->app->make('webhook-manager');

        foreach ((array) config('webhook-manager.event_triggers', []) as $key => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $eventClass = $definition['event'] ?? null;
            if (! is_string($eventClass) || $eventClass === '') {
                continue;
            }

            $definition['handle'] ??= is_string($key) ? $key : $eventClass;

            $manager->registerEventTrigger($eventClass, $definition);
        }
    }

    protected function bootWebhookPublishables(): void
    {
        $this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/webhook-manager'),
        ], 'webhook-manager-lang');

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'webhook-manager');
    }
}
