<?php

use Goldnead\WebhookManager\Http\Controllers\InboundWebhookController;
use Goldnead\WebhookManager\WebhookManagerServiceProvider;
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
            //
            // Two shapes, and the brand-qualified one is registered first
            // because `{handle}` would otherwise swallow a brand segment and
            // look for an endpoint by that name.
            //
            // The brand belongs in the path because there is nowhere else it
            // can come from: the sender is Scaleway or Stripe or n8n, it holds
            // a URL and nothing else, and `InboundEndpoint` is brand-scoped
            // with a fail-closed global scope. See ResolveInboundBrand — before
            // it existed, every inbound delivery on a multi-brand install was
            // answered with 404 while the endpoint sat in the table.
            // One pattern, one place. `inboundPath()` decides from the same
            // constant whether the URL it prints can be matched; two literals
            // would drift, and a printed URL that the router rejects is the
            // fault this release exists to end.
            $segment = WebhookManagerServiceProvider::INBOUND_SEGMENT_PATTERN;

            Route::any('{brand}/{handle}', InboundWebhookController::class)
                ->where('brand', $segment)
                ->where('handle', $segment)
                ->name('branded');

            Route::any('{handle}', InboundWebhookController::class)
                ->where('handle', $segment)
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
