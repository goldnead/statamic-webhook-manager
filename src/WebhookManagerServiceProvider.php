<?php

namespace Goldnead\WebhookManager;

use Goldnead\WebhookManager\Console\Commands\InspectWebhookHealthCommand;
use Goldnead\WebhookManager\Console\Commands\PruneWebhookDataCommand;
use Goldnead\WebhookManager\Console\Commands\ReplayFailedDeliveriesCommand;
use Goldnead\WebhookManager\Console\Commands\SeedWebhookExamplesCommand;
use Goldnead\WebhookManager\Console\Commands\StorageMigrateCommand;
use Goldnead\WebhookManager\Events\TriggerDetected;
use Goldnead\WebhookManager\Listeners\DispatchTriggerListener;
use Goldnead\WebhookManager\Listeners\HandleAssetSavedListener;
use Goldnead\WebhookManager\Listeners\HandleEntryDeletedListener;
use Goldnead\WebhookManager\Listeners\HandleEntryPublishedListener;
use Goldnead\WebhookManager\Listeners\HandleEntrySavedListener;
use Goldnead\WebhookManager\Listeners\HandleEntryUnpublishedListener;
use Goldnead\WebhookManager\Listeners\HandleFormSubmittedListener;
use Goldnead\WebhookManager\Listeners\HandleUserSavedListener;
use Goldnead\WebhookManager\Registries\ActionRegistry;
use Goldnead\WebhookManager\Registries\AuthSchemeRegistry;
use Goldnead\WebhookManager\Registries\ConditionRegistry;
use Goldnead\WebhookManager\Registries\InboundActionHandlerRegistry;
use Goldnead\WebhookManager\Registries\SuccessEvaluatorRegistry;
use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Goldnead\WebhookManager\Registries\VariableResolverRegistry;
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
        \Goldnead\WebhookManager\Events\DeliveryFailedTerminally::class => [
            \Goldnead\WebhookManager\Listeners\SendFailureAlertListener::class,
        ],
    ];

    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
        'web' => __DIR__.'/../routes/inbound.php',
    ];

    protected $commands = [
        PruneWebhookDataCommand::class,
        ReplayFailedDeliveriesCommand::class,
        InspectWebhookHealthCommand::class,
        SeedWebhookExamplesCommand::class,
        StorageMigrateCommand::class,
    ];

    /**
     * Native Statamic CP actions. Auto-discovery only scans the top level of
     * src/Actions/ (which holds the internal rule actions), so the entry
     * action under Actions/Cp/ is registered explicitly here.
     */
    protected $actions = [
        \Goldnead\WebhookManager\Actions\Cp\SendWebhook::class,
    ];

    public function bootAddon(): void
    {
        $this->bootWebhookConfig();
        $this->bootMigrations();
        $this->bootBindings();
        $this->bootWebhookPublishables();
        $this->bootPermissions();
        $this->bootNavigation();
        $this->bootRegistries();
        $this->bootRouteBindings();
    }

    /**
     * Resolve the config-entity route parameters through the repository
     * layer instead of Eloquent implicit binding, so CP routes work under
     * both the database and the flat-file storage driver. Delivery/log
     * params remain database-bound (those tables are always Eloquent).
     */
    protected function bootRouteBindings(): void
    {
        $bindings = [
            'webhook' => \Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface::class,
            'endpoint' => \Goldnead\WebhookManager\Contracts\Repositories\InboundEndpointRepositoryInterface::class,
            'rule' => \Goldnead\WebhookManager\Contracts\Repositories\RuleRepositoryInterface::class,
            'template' => \Goldnead\WebhookManager\Contracts\Repositories\TemplateRepositoryInterface::class,
        ];

        foreach ($bindings as $param => $contract) {
            \Illuminate\Support\Facades\Route::bind($param, function ($value) use ($contract) {
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
        $this->app->singleton(\Goldnead\WebhookManager\Registries\PresetRegistry::class);

        $this->app->singleton(\Goldnead\WebhookManager\Auth\Support\ReplayProtectionService::class, function ($app) {
            return new \Goldnead\WebhookManager\Auth\Support\ReplayProtectionService(
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
        $this->app->singleton(\Goldnead\WebhookManager\Storage\FileStore::class, function () {
            return new \Goldnead\WebhookManager\Storage\FileStore(
                (string) config('webhook-manager.storage.flat.path', base_path('content/webhooks')),
            );
        });

        $this->app->singleton(\Goldnead\WebhookManager\Storage\ModelHydrator::class);
        $this->app->singleton(\Goldnead\WebhookManager\Storage\StorageDriverManager::class);
        $this->app->singleton(\Goldnead\WebhookManager\Storage\StorageMigrator::class);

        $map = [
            \Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface::class => [
                \Goldnead\WebhookManager\Repositories\Eloquent\EloquentOutboundWebhookRepository::class,
                \Goldnead\WebhookManager\Repositories\FlatFile\FlatFileOutboundWebhookRepository::class,
            ],
            \Goldnead\WebhookManager\Contracts\Repositories\InboundEndpointRepositoryInterface::class => [
                \Goldnead\WebhookManager\Repositories\Eloquent\EloquentInboundEndpointRepository::class,
                \Goldnead\WebhookManager\Repositories\FlatFile\FlatFileInboundEndpointRepository::class,
            ],
            \Goldnead\WebhookManager\Contracts\Repositories\RuleRepositoryInterface::class => [
                \Goldnead\WebhookManager\Repositories\Eloquent\EloquentRuleRepository::class,
                \Goldnead\WebhookManager\Repositories\FlatFile\FlatFileRuleRepository::class,
            ],
            \Goldnead\WebhookManager\Contracts\Repositories\TemplateRepositoryInterface::class => [
                \Goldnead\WebhookManager\Repositories\Eloquent\EloquentTemplateRepository::class,
                \Goldnead\WebhookManager\Repositories\FlatFile\FlatFileTemplateRepository::class,
            ],
        ];

        foreach ($map as $contract => [$eloquent, $flat]) {
            $this->app->bind($contract, function ($app) use ($eloquent, $flat) {
                // The active driver comes from the StorageDriverManager, which
                // prefers a Control-Panel-persisted choice over config/env.
                return $app->make(\Goldnead\WebhookManager\Storage\StorageDriverManager::class)->current() === 'flat'
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

        /** @var \Goldnead\WebhookManager\Registries\PresetRegistry $presets */
        $presets = $this->app->make(\Goldnead\WebhookManager\Registries\PresetRegistry::class);
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
