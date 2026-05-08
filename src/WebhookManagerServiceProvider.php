<?php

namespace Goldnead\WebhookManager;

use Goldnead\WebhookManager\Console\Commands\InspectWebhookHealthCommand;
use Goldnead\WebhookManager\Console\Commands\PruneWebhookDataCommand;
use Goldnead\WebhookManager\Console\Commands\ReplayFailedDeliveriesCommand;
use Goldnead\WebhookManager\Console\Commands\SeedWebhookExamplesCommand;
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
    ];

    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
        'actions' => __DIR__.'/../routes/actions.php',
        'web' => __DIR__.'/../routes/inbound.php',
    ];

    protected $commands = [
        PruneWebhookDataCommand::class,
        ReplayFailedDeliveriesCommand::class,
        InspectWebhookHealthCommand::class,
        SeedWebhookExamplesCommand::class,
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

        $this->app->singleton(\Goldnead\WebhookManager\Auth\Support\ReplayProtectionService::class, function ($app) {
            return new \Goldnead\WebhookManager\Auth\Support\ReplayProtectionService(
                $app['cache.store'],
                (int) config('webhook-manager.inbound.replay_protection_ttl_seconds', 600),
            );
        });

        $this->app->singleton('webhook-manager', WebhookManager::class);
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
            $nav->create(__('webhook-manager::nav.webhooks'))
                ->section(__('webhook-manager::nav.section'))
                ->route('webhook-manager.overview')
                ->icon('hyperlink')
                ->can('view webhooks')
                ->children([
                    $nav->item(__('webhook-manager::nav.overview'))->route('webhook-manager.overview'),
                    $nav->item(__('webhook-manager::nav.outbound'))->route('webhook-manager.outbound.index')->can('manage outbound webhooks'),
                    $nav->item(__('webhook-manager::nav.inbound'))->route('webhook-manager.inbound.index')->can('manage inbound endpoints'),
                    $nav->item(__('webhook-manager::nav.rules'))->route('webhook-manager.rules.index')->can('manage webhook rules'),
                    $nav->item(__('webhook-manager::nav.deliveries'))->route('webhook-manager.deliveries.index')->can('view webhook deliveries'),
                    $nav->item(__('webhook-manager::nav.logs'))->route('webhook-manager.logs.index')->can('view webhooks'),
                    $nav->item(__('webhook-manager::nav.templates'))->route('webhook-manager.templates.index')->can('manage webhook templates'),
                    $nav->item(__('webhook-manager::nav.settings'))->route('webhook-manager.settings')->can('manage webhook settings'),
                    $nav->item(__('webhook-manager::nav.debug'))->route('webhook-manager.debug')->can('use webhook debug tools'),
                ]);
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
    }

    protected function bootWebhookPublishables(): void
    {
        $this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/webhook-manager'),
        ], 'webhook-manager-lang');

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'webhook-manager');
    }
}
