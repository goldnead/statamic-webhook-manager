<?php

use Goldnead\WebhookManager\Http\Controllers\InboundWebhookController;
use Illuminate\Support\Facades\Route;

$prefix = trim((string) config('webhook-manager.inbound.route_prefix', '!/webhooks/inbound'), '/');
$middleware = (array) config('webhook-manager.inbound.middleware', ['web']);

Route::middleware($middleware)
    ->prefix($prefix)
    ->name('webhook-manager.inbound.')
    ->group(function () {
        // External services POST here without a CSRF token; endpoint auth is
        // handled by the configured verifier (HMAC, static header, ...).
        Route::any('{handle}', InboundWebhookController::class)
            ->where('handle', '[a-z0-9_-]+')
            ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
            ->name('handle');
    });
