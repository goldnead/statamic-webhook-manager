<?php

use Goldnead\WebhookManager\Http\Controllers\Cp\DebugController;
use Goldnead\WebhookManager\Http\Controllers\Cp\DeliveryController;
use Goldnead\WebhookManager\Http\Controllers\Cp\InboundController;
use Goldnead\WebhookManager\Http\Controllers\Cp\LogController;
use Goldnead\WebhookManager\Http\Controllers\Cp\OutboundController;
use Goldnead\WebhookManager\Http\Controllers\Cp\OverviewController;
use Goldnead\WebhookManager\Http\Controllers\Cp\RuleController;
use Goldnead\WebhookManager\Http\Controllers\Cp\SettingsController;
use Goldnead\WebhookManager\Http\Controllers\Cp\TemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('webhook-manager')->name('webhook-manager.')->group(function () {
    Route::get('/', [OverviewController::class, 'index'])->name('overview');

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
    });

    Route::prefix('deliveries')->name('deliveries.')->group(function () {
        Route::get('/', [DeliveryController::class, 'index'])->name('index');
        Route::get('/{delivery}', [DeliveryController::class, 'show'])->name('show');
    });

    Route::prefix('logs')->name('logs.')->group(function () {
        Route::get('/', [LogController::class, 'index'])->name('index');
    });

    Route::prefix('templates')->name('templates.')->group(function () {
        Route::get('/', [TemplateController::class, 'index'])->name('index');
    });

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::get('/debug', [DebugController::class, 'index'])->name('debug');
});
