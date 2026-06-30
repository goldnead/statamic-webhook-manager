<?php

use Goldnead\WebhookManager\Http\Controllers\Cp\Actions\PreviewTemplateController;
use Goldnead\WebhookManager\Http\Controllers\Cp\Actions\ReplayDeliveryController;
use Goldnead\WebhookManager\Http\Controllers\Cp\Actions\SimulateTriggerController;
use Goldnead\WebhookManager\Http\Controllers\Cp\Actions\TestInboundController;
use Goldnead\WebhookManager\Http\Controllers\Cp\Actions\TestOutboundController;
use Goldnead\WebhookManager\Http\Controllers\Cp\Actions\TestRuleController;
use Goldnead\WebhookManager\Http\Controllers\Cp\DebugController;
use Goldnead\WebhookManager\Http\Controllers\Cp\DeliveryController;
use Goldnead\WebhookManager\Http\Controllers\Cp\InboundController;
use Goldnead\WebhookManager\Http\Controllers\Cp\InsightsController;
use Goldnead\WebhookManager\Http\Controllers\Cp\LogController;
use Goldnead\WebhookManager\Http\Controllers\Cp\OutboundController;
use Goldnead\WebhookManager\Http\Controllers\Cp\OverviewController;
use Goldnead\WebhookManager\Http\Controllers\Cp\PresetController;
use Goldnead\WebhookManager\Http\Controllers\Cp\RuleController;
use Goldnead\WebhookManager\Http\Controllers\Cp\SettingsController;
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
        Route::get('/create', [OutboundController::class, 'create'])->name('create');
        Route::post('/', [OutboundController::class, 'store'])->name('store');
        Route::get('/{webhook}', [OutboundController::class, 'edit'])->name('edit');
        Route::patch('/{webhook}', [OutboundController::class, 'update'])->name('update');
        Route::delete('/{webhook}', [OutboundController::class, 'destroy'])->name('destroy');
        Route::patch('/{webhook}/toggle', [OutboundController::class, 'toggle'])->name('toggle');
    });

    Route::prefix('inbound')->name('inbound.')->group(function () {
        Route::get('/', [InboundController::class, 'index'])->name('index');
        Route::get('/create', [InboundController::class, 'create'])->name('create');
        Route::post('/', [InboundController::class, 'store'])->name('store');
        Route::get('/{endpoint}', [InboundController::class, 'edit'])->name('edit');
        Route::patch('/{endpoint}', [InboundController::class, 'update'])->name('update');
        Route::delete('/{endpoint}', [InboundController::class, 'destroy'])->name('destroy');
        Route::patch('/{endpoint}/toggle', [InboundController::class, 'toggle'])->name('toggle');
    });

    Route::prefix('rules')->name('rules.')->group(function () {
        Route::get('/', [RuleController::class, 'index'])->name('index');
        Route::get('/create', [RuleController::class, 'create'])->name('create');
        Route::post('/', [RuleController::class, 'store'])->name('store');
        Route::get('/{rule}', [RuleController::class, 'edit'])->name('edit');
        Route::patch('/{rule}', [RuleController::class, 'update'])->name('update');
        Route::delete('/{rule}', [RuleController::class, 'destroy'])->name('destroy');
        Route::patch('/{rule}/toggle', [RuleController::class, 'toggle'])->name('toggle');
    });

    Route::get('/insights', [InsightsController::class, 'index'])->name('insights');

    Route::prefix('deliveries')->name('deliveries.')->group(function () {
        Route::get('/', [DeliveryController::class, 'index'])->name('index');
        Route::get('/{delivery}', [DeliveryController::class, 'show'])->name('show');
    });

    Route::prefix('logs')->name('logs.')->group(function () {
        Route::get('/', [LogController::class, 'index'])->name('index');
    });

    Route::prefix('templates')->name('templates.')->group(function () {
        Route::get('/', [TemplateController::class, 'index'])->name('index');
        Route::get('/create', [TemplateController::class, 'create'])->name('create');
        Route::post('/', [TemplateController::class, 'store'])->name('store');
        Route::get('/{template}', [TemplateController::class, 'edit'])->name('edit');
        Route::patch('/{template}', [TemplateController::class, 'update'])->name('update');
        Route::delete('/{template}', [TemplateController::class, 'destroy'])->name('destroy');
    });

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('/settings/storage', [SettingsController::class, 'switchStorage'])->name('settings.storage');
    Route::get('/debug', [DebugController::class, 'index'])->name('debug');

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
        Route::post('outbound/{webhook}/test', TestOutboundController::class)->name('test-outbound');
        Route::post('inbound/{endpoint}/test', TestInboundController::class)->name('test-inbound');
        Route::post('rules/{rule}/test', TestRuleController::class)->name('test-rule');
        Route::post('deliveries/{delivery}/replay', ReplayDeliveryController::class)->name('replay-delivery');
        Route::post('templates/preview', PreviewTemplateController::class)->name('preview-template');
        Route::post('triggers/simulate', SimulateTriggerController::class)->name('simulate-trigger');
    });
});
