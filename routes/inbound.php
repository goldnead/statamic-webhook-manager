<?php

use Goldnead\WebhookManager\Http\Controllers\InboundWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Inbound webhook endpoint
|--------------------------------------------------------------------------
|
| This file is NOT registered through Statamic's `$routes['web']` hook.
| That hook drops every route it registers inside the application's `web`
| middleware group (statamic/cms/routes/routes.php wraps its whole web
| route file in `config('statamic.routes.middleware', 'web')`), and `web`
| is the wrong stack for a machine-to-machine endpoint:
|
|   ValidateCsrfToken     — an external sender has no session token: 419
|   StartSession          — a session (and session file) per delivery, never read
|   EncryptCookies        — no cookies involved
|   ShareErrorsFromSession
|   HandleInertiaRequests — an Inertia envelope no webhook sender understands
|   plus whatever the host app appends (UpdateLastSeen, redirect handlers, …)
|
| The previous fix was `->withoutMiddleware([ValidateCsrfToken::class])`,
| which removes exactly one of those, and only for as long as the host app
| keeps using that precise class name. This file instead declares the full
| stack the endpoint runs, so nothing is inherited and nothing has to be
| undone.
|
| `WebhookManagerServiceProvider::bootInboundRoutes()` loads this file during
| the addon's boot phase — before Statamic loads its own route files from an
| `$app->booted()` callback — so these routes are matched ahead of Statamic's
| front-end catch-all `Route::any('/{segments?}')`.
|
| Variables are provided by the service provider:
|
| @var string                    $prefix          canonical route prefix
| @var array<int, class-string>  $middleware      the complete middleware stack
| @var array<int, string>        $legacyPrefixes  additional prefixes kept alive
*/

$register = function (string $prefix, string $name) use ($middleware): void {
    Route::middleware($middleware)
        ->prefix($prefix)
        ->name($name)
        ->group(function () {
            // External services POST here without a CSRF token. Endpoint auth
            // is enforced by the configured verifier (static header, bearer,
            // basic, HMAC) inside InboundRequestProcessor, before any parsing,
            // mapping or action dispatch happens.
            Route::any('{handle}', InboundWebhookController::class)
                ->where('handle', '[a-z0-9_-]+')
                ->name('handle');
        });
};

$register($prefix, 'webhook-manager.inbound.');

// Before v1.8.0 the endpoint was published under Statamic's `!/` utility
// prefix. Senders already pointed at that URL keep working; the CP only ever
// shows the canonical one.
foreach (array_values($legacyPrefixes) as $i => $legacyPrefix) {
    if ($legacyPrefix === $prefix || $legacyPrefix === '') {
        continue;
    }

    $register($legacyPrefix, 'webhook-manager.inbound.legacy'.($i > 0 ? $i : '').'.');
}
