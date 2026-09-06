<?php

use Goldnead\WebhookManager\Http\Controllers\Cp\Actions\PreviewTemplateController;
use Goldnead\WebhookManager\Http\Controllers\Cp\Actions\ReplayDeliveryController;
use Goldnead\WebhookManager\Http\Controllers\Cp\Actions\SimulateTriggerController;
use Goldnead\WebhookManager\Http\Controllers\Cp\Actions\TestInboundController;
use Goldnead\WebhookManager\Http\Controllers\Cp\Actions\TestOutboundController;
use Goldnead\WebhookManager\Http\Controllers\Cp\Actions\TestRuleController;
use Goldnead\WebhookManager\Http\Controllers\Cp\DebugController;
use Goldnead\WebhookManager\Http\Controllers\Cp\DeliveryActionController;
use Goldnead\WebhookManager\Http\Controllers\Cp\DeliveryController;
use Goldnead\WebhookManager\Http\Controllers\Cp\InboundController;
use Goldnead\WebhookManager\Http\Controllers\Cp\InsightsController;
use Goldnead\WebhookManager\Http\Controllers\Cp\LogController;
use Goldnead\WebhookManager\Http\Controllers\Cp\OutboundActionController;
use Goldnead\WebhookManager\Http\Controllers\Cp\OutboundController;
use Goldnead\WebhookManager\Http\Controllers\Cp\OverviewController;
use Goldnead\WebhookManager\Http\Controllers\Cp\PresetController;
use Goldnead\WebhookManager\Http\Controllers\Cp\RuleController;
use Goldnead\WebhookManager\Http\Controllers\Cp\TemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('webhook-manager')->name('webhook-manager.')->group(function () {
    Route::get('/', [OverviewController::class, 'index'])->name('overview');

    Route::prefix('integrations')->name('integrations.')->group(function () {
        Route::get('/', [PresetController::class, 'index'])->name('index');
        Route::get('/{preset}', [PresetController::class, 'create'])->name('create');
        Route::post('/{preset}', [PresetController::class, 'store'])->name('store');
    });

    Route::prefix('outbound')->name('outbound.')->group(function () {
        Route::get('/', [OutboundController::class, 'index'])->name('index');
        // The two endpoints core's <Listing> posts to for row and bulk actions.
        // Declared before `{webhookOutbound}` so the literal segment cannot be
        // read as a webhook id, the same reason `for-subject` sits first in the
        // deliveries group.
        Route::post('/actions', [OutboundActionController::class, 'run'])->name('actions.run');
        Route::post('/actions/list', [OutboundActionController::class, 'bulkActions'])->name('actions.list');
        Route::get('/create', [OutboundController::class, 'create'])->name('create');
        Route::post('/', [OutboundController::class, 'store'])->name('store');
        Route::get('/{webhookOutbound}', [OutboundController::class, 'edit'])->name('edit');
        Route::patch('/{webhookOutbound}', [OutboundController::class, 'update'])->name('update');
        Route::delete('/{webhookOutbound}', [OutboundController::class, 'destroy'])->name('destroy');
        Route::patch('/{webhookOutbound}/toggle', [OutboundController::class, 'toggle'])->name('toggle');
    });

    Route::prefix('inbound')->name('inbound.')->group(function () {
        Route::get('/', [InboundController::class, 'index'])->name('index');
        Route::get('/create', [InboundController::class, 'create'])->name('create');
        Route::post('/', [InboundController::class, 'store'])->name('store');
        Route::get('/{webhookInbound}', [InboundController::class, 'edit'])->name('edit');
        Route::patch('/{webhookInbound}', [InboundController::class, 'update'])->name('update');
        Route::delete('/{webhookInbound}', [InboundController::class, 'destroy'])->name('destroy');
        Route::patch('/{webhookInbound}/toggle', [InboundController::class, 'toggle'])->name('toggle');
    });

    Route::prefix('rules')->name('rules.')->group(function () {
        Route::get('/', [RuleController::class, 'index'])->name('index');
        Route::get('/create', [RuleController::class, 'create'])->name('create');
        Route::post('/', [RuleController::class, 'store'])->name('store');
        Route::get('/{webhookRule}', [RuleController::class, 'edit'])->name('edit');
        Route::patch('/{webhookRule}', [RuleController::class, 'update'])->name('update');
        Route::delete('/{webhookRule}', [RuleController::class, 'destroy'])->name('destroy');
        Route::patch('/{webhookRule}/toggle', [RuleController::class, 'toggle'])->name('toggle');
    });

    Route::get('/insights', [InsightsController::class, 'index'])->name('insights');

    Route::prefix('deliveries')->name('deliveries.')->group(function () {
        Route::get('/', [DeliveryController::class, 'index'])->name('index');
        // Before `{delivery}`, or the literal segment binds as a delivery id.
        Route::get('/for-subject', [DeliveryController::class, 'forSubject'])->name('for-subject');
        Route::post('/actions', [DeliveryActionController::class, 'run'])->name('actions.run');
        Route::post('/actions/list', [DeliveryActionController::class, 'bulkActions'])->name('actions.list');
        Route::get('/{delivery}', [DeliveryController::class, 'show'])->name('show');
    });

    Route::prefix('logs')->name('logs.')->group(function () {
        Route::get('/', [LogController::class, 'index'])->name('index');
    });

    Route::prefix('templates')->name('templates.')->group(function () {
        Route::get('/', [TemplateController::class, 'index'])->name('index');
        Route::get('/create', [TemplateController::class, 'create'])->name('create');
        Route::post('/', [TemplateController::class, 'store'])->name('store');
        Route::get('/{webhookTemplate}', [TemplateController::class, 'edit'])->name('edit');
        Route::patch('/{webhookTemplate}', [TemplateController::class, 'update'])->name('update');
        Route::delete('/{webhookTemplate}', [TemplateController::class, 'destroy'])->name('destroy');
    });

    /*
     * The settings screen moved to the suite's shared one in
     * statamic-brand-context. This route stays as a redirect rather than being
     * deleted: `/cp/webhook-manager/settings` is in bookmarks, in the docs and
     * in the sidebar, and a 404 there tells nobody where the settings went.
     *
     * A redirect, not a nav item pointed straight at the shared route: one
     * place in this package knows the new address, and both the sidebar and
     * every old link go through it.
     *
     * `cp_route()` is resolved inside the closure, not while the file is read.
     * Route files are evaluated before every provider has registered its own,
     * and route:cache evaluates them all in one pass — resolving a sibling
     * package's route name at file level would throw during caching on the
     * unlucky ordering.
     */
    Route::get('/settings', fn () => redirect(cp_route('brand-context.settings.index')))->name('settings');
    Route::get('/debug', [DebugController::class, 'index'])->name('debug');
    // The storage driver switch, which was never a setting: it moves the stored
    // webhook configuration between stores and only then flips the flag. It
    // came over from the deleted SettingsController with the Debug screen and
    // keeps its own permission (`manage webhook settings`).
    Route::post('/debug/storage', [DebugController::class, 'switchStorage'])->name('debug.storage');

    /*
     * Action endpoints (POST handlers for the test/replay/preview/simulate
     * buttons in the CP). Registered here under the same `webhook-manager.`
     * route group as the index/edit pages so that:
     *
     *   1. cp_route('webhook-manager.actions.test-outbound', $hook)
     *      resolves correctly — Statamic prefixes the lookup with
     *      `statamic.cp.`, so the full registered name becomes
     *      `statamic.cp.webhook-manager.actions.test-outbound`.
     *
     *   2. URLs land at /cp/webhook-manager/{slug}/{id}/{action} which is
     *      the natural sibling of the edit pages and benefits from the CP
     *      auth middleware stack.
     *
     * Previously these lived in `routes/actions.php` registered via
     * AddonServiceProvider's `actions` route key, which prefixed the URL
     * with the addon slug AGAIN (resulting in
     * /webhook-manager/webhook-manager/...) and also did NOT prefix the
     * route name with `statamic.cp.`, breaking every cp_route() lookup.
     */
    Route::name('actions.')->group(function () {
        Route::post('outbound/{webhookOutbound}/test', TestOutboundController::class)->name('test-outbound');
        Route::post('inbound/{webhookInbound}/test', TestInboundController::class)->name('test-inbound');
        Route::post('rules/{webhookRule}/test', TestRuleController::class)->name('test-rule');
        Route::post('deliveries/{delivery}/replay', ReplayDeliveryController::class)->name('replay-delivery');
        Route::post('templates/preview', PreviewTemplateController::class)->name('preview-template');
        Route::post('triggers/simulate', SimulateTriggerController::class)->name('simulate-trigger');
    });
});
