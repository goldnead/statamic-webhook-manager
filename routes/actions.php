<?php

use Goldnead\WebhookManager\Http\Controllers\Cp\Actions\PreviewTemplateController;
use Goldnead\WebhookManager\Http\Controllers\Cp\Actions\ReplayDeliveryController;
use Goldnead\WebhookManager\Http\Controllers\Cp\Actions\SimulateTriggerController;
use Goldnead\WebhookManager\Http\Controllers\Cp\Actions\TestInboundController;
use Goldnead\WebhookManager\Http\Controllers\Cp\Actions\TestOutboundController;
use Illuminate\Support\Facades\Route;

Route::prefix('webhook-manager')->name('webhook-manager.actions.')->group(function () {
    Route::post('outbound/{webhook}/test', TestOutboundController::class)->name('test-outbound');
    Route::post('inbound/{endpoint}/test', TestInboundController::class)->name('test-inbound');
    Route::post('deliveries/{delivery}/replay', ReplayDeliveryController::class)->name('replay-delivery');
    Route::post('templates/preview', PreviewTemplateController::class)->name('preview-template');
    Route::post('triggers/simulate', SimulateTriggerController::class)->name('simulate-trigger');
});
