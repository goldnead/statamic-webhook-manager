<?php

namespace Goldnead\WebhookManager\Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;

/**
 * Base class for tests that exercise the REAL Control-Panel request path:
 * route → FormRequest → controller → action → repository.
 *
 * That path is where the secret-dropping bug lived. It could not be caught by
 * any model- or action-level test, because the value was already gone by the
 * time the action was called: `SaveOutboundWebhookRequest::rules()` did not
 * list `auth_config_json`, and the controllers persist `$request->validated()`.
 *
 * Statamic pushes the addon's CP routes during its own boot lifecycle, which
 * orchestra/testbench does not run. They are registered here manually, under
 * the same `statamic.cp.` name prefix Statamic uses, so `cp_route()` inside
 * the controllers resolves exactly as it does in a real install.
 */
abstract class CpTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // cp_route() returns null when the CP is disabled, which would make
        // every controller blow up on a null route.
        $app['config']->set('statamic.cp.enabled', true);
    }

    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        $router->middleware([
            StartSession::class,      // flash messages / redirect->with('success')
            SubstituteBindings::class, // the addon's Route::bind() resolvers
        ])
            ->prefix('cp')
            ->name('statamic.cp.')
            ->group(__DIR__.'/../routes/cp.php');
    }

    /**
     * A CP user with an explicit ability list.
     *
     * Deliberately NOT a Gate-backed user: the point of several of these
     * tests is what `$user->can(...)` returns for an ability, including one
     * that is not registered at all.
     *
     * @param  array<int,string>  $abilities
     */
    protected function cpUser(array $abilities): Authenticatable
    {
        return new FakeCpUser($abilities);
    }

    /** Every ability the addon registers — the "super user" stand-in. */
    protected function superUser(): Authenticatable
    {
        return $this->cpUser([
            'view webhooks',
            'manage outbound webhooks',
            'test outbound webhooks',
            'manage inbound endpoints',
            'manage webhook rules',
            'view webhook deliveries',
            'replay webhook deliveries',
            'view sensitive payloads',
            'manage webhook settings',
            'manage webhook templates',
            'use webhook debug tools',
        ]);
    }

    /**
     * Headers that make Inertia answer with its JSON protocol instead of
     * rendering the (nonexistent in testbench) root Blade view.
     *
     * @return array<string,string>
     */
    protected function inertiaHeaders(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => '1',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }
}
