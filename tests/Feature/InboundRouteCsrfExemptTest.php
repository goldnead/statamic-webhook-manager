<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Tests\TestCase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

/**
 * External services POST to inbound endpoints without a CSRF token. The
 * route runs in the `web` middleware group by default, so it must
 * explicitly exclude the CSRF middleware — otherwise every real-world
 * webhook delivery dies with a 419.
 *
 * Laravel's ValidateCsrfToken auto-skips while running unit tests, so a
 * plain HTTP assertion cannot reproduce the 419; this test locks the
 * exclusion in structurally instead.
 */
class InboundRouteCsrfExemptTest extends TestCase
{
    public function test_inbound_handle_route_excludes_csrf_middleware(): void
    {
        $route = Route::getRoutes()->getByName('webhook-manager.inbound.handle');

        $this->assertNotNull($route, 'inbound route is registered');
        $this->assertContains(ValidateCsrfToken::class, $route->excludedMiddleware());
    }
}
