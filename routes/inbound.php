<?php

use Goldnead\WebhookManager\Http\Controllers\InboundWebhookController;
use Illuminate\Support\Facades\Route;

$prefix = trim((string) config('webhook-manager.inbound.route_prefix', '!/webhooks/inbound'), '/');
$middleware = (array) config('webhook-manager.inbound.middleware', ['web']);

Route::middleware($middleware)
    ->prefix($prefix)
    ->name('webhook-manager.inbound.')
    ->group(function () {
        Route::any('{handle}', InboundWebhookController::class)
            ->where('handle', '[a-z0-9_-]+')
            ->name('handle');
    });
