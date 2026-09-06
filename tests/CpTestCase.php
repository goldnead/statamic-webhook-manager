<?php

namespace Goldnead\WebhookManager\Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Statamic\Facades\Permission;

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

        // The suite's shared settings screen, from statamic-brand-context. Its
        // provider pushes these the same way Statamic pushes ours — inside the
        // boot lifecycle testbench does not run — so they are registered by
        // hand here too. Without them `/cp/webhook-manager/settings` redirects
        // to a route name that does not exist, which is a failure of the test
        // bed rather than of the redirect.
        $router->middleware([StartSession::class, SubstituteBindings::class])
            ->prefix('cp')
            ->name('statamic.cp.')
            ->group($this->brandContextPath().'/routes/cp-settings.php');

        // `statamic.cp.addons.index` is a core CP route
        // (statamic/cms routes/cp.php: `Route::get('addons', …)`), and the
        // shared settings screen points its empty state at it. testbench
        // registers none of core's CP routes, so it is stubbed rather than
        // faked: nothing in this suite follows it, and without it every render
        // of that screen dies on a route lookup that always succeeds in a real
        // install.
        $router->get('cp/addons', fn () => '')->name('statamic.cp.addons.index');
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

    /**
     * Every ability the addon registers — the "super user" stand-in.
     *
     * Read out of the registry rather than typed out, so a newly registered
     * permission is granted here automatically and a renamed one cannot leave
     * a stale literal behind. A hand-maintained copy of this list is the same
     * failure mode as the `manage rules` typo: it drifts silently and the
     * tests keep passing.
     */
    protected function superUser(): Authenticatable
    {
        return $this->cpUser($this->registeredAbilities());
    }

    /** @return array<int,string> */
    protected function registeredAbilities(): array
    {
        return Permission::all()
            ->filter(fn ($permission) => $permission->group() === 'webhook_manager')
            ->map(fn ($permission) => $permission->value())
            ->filter(fn ($value) => is_string($value))
            ->values()
            ->all();
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
