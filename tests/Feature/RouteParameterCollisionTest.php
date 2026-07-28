<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Tests\CpTestCase;
use Illuminate\Support\Facades\Route;

/**
 * Route parameter names are one namespace shared by the whole application.
 *
 * This addon is the one that made that concrete for the rest of the family.
 * `bootRouteBindings()` registers `Route::bind()` for `webhook`, `endpoint`,
 * `rule` and `template` so the CP routes resolve through the repository layer
 * under both storage drivers. Those bindings are registered on the router, not
 * on this package: they apply to every route with one of those parameter names
 * in every addon installed alongside.
 *
 * goldnead/statamic-leadhub 1.8.0 shipped `/scoring/{rule}`. On the Hub, which
 * has both addons, its edit and delete resolved against the rule repository
 * here, which has never heard of a LeadHub id, and returned 404 — a delete
 * button that did nothing and said nothing. LeadHub's own suite was green
 * throughout, for two reasons that are both structural:
 *
 *   1. This addon is not installed in that addon's test bed, so the binding
 *      does not exist there at all.
 *   2. That bed mounted its CP routes without `SubstituteBindings`, so no
 *      `Route::bind()` had any effect in tests even when one was registered.
 *
 * This addon's CP bed has always carried the middleware — it has to, the
 * bindings are its own. The first test pins that, so the property cannot be
 * lost quietly. The other two check the parameter names.
 *
 * What this file cannot do: a collision only exists once two packages are
 * installed together, and a package cannot see its siblings from inside its own
 * suite. The reserved list below is a snapshot maintained by hand, and it will
 * not catch an addon that starts binding a name nobody binds today. That is why
 * the third test exists — it forces any new generic parameter to be a decision
 * somebody wrote down rather than a default nobody looked at.
 *
 * Note what is deliberately NOT done here: the four bound names are not
 * renamed. They are this addon's own, the URLs would be identical either way,
 * and no sibling uses them any more. The exposure that remains is that any
 * FUTURE addon reaching for `{rule}` or `{template}` loses silently — which is
 * what the equivalent of this file, in that addon, is for.
 */
class RouteParameterCollisionTest extends CpTestCase
{
    /**
     * Every route parameter this addon declares, read from the route files
     * rather than the router, so the check covers routes regardless of how the
     * test bed happens to mount them.
     *
     * Only string literals are scanned. routes/cp.php documents the action
     * endpoints with an example URL, `/cp/webhook-manager/{slug}/{id}/{action}`,
     * and a plain regex over the file text reports those three as parameters.
     *
     * @return array<string, list<string>> parameter name => route files using it
     */
    private function routeParameters(): array
    {
        $found = [];

        foreach (['cp.php', 'inbound.php', 'web.php'] as $file) {
            $path = __DIR__.'/../../routes/'.$file;

            if (! is_file($path)) {
                continue;
            }

            foreach (token_get_all((string) file_get_contents($path)) as $token) {
                if (! is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                    continue;
                }

                preg_match_all('/\{([A-Za-z0-9_]+)\??\}/', $token[1], $matches);

                foreach ($matches[1] as $parameter) {
                    $found[$parameter][] = $file;
                }
            }
        }

        return array_map('array_unique', $found);
    }

    public function test_the_cp_bed_mounts_substitute_bindings_so_route_bindings_apply(): void
    {
        // Without SubstituteBindings in CpTestCase::defineRoutes() this callback
        // is never invoked and the request resolves normally — and with it gone,
        // every binding this addon registers would be inert in every test here.
        // Re-registering `rule` replaces the addon's own binder for this test.
        // SubstituteBindings runs as middleware, ahead of the controller, so a
        // 418 here proves the middleware ran — with it absent the request
        // reaches RuleController and answers something else entirely.
        Route::bind('rule', function ($value) {
            abort(418, 'binding reached');
        });

        $this->get('/cp/webhook-manager/rules/does-not-exist')->assertStatus(418);
    }

    public function test_it_uses_no_route_parameter_another_installed_package_binds(): void
    {
        // Names bound application-wide by packages this addon is installed
        // beside. Read off the running Hub: statamic/cms registers the CMS
        // entity bindings, statamic-automations registers {automation}.
        // This addon's own four are absent — it owns them.
        // Maintained by hand; see the class docblock for what that costs.
        $boundElsewhere = [
            'automation' => 'goldnead/statamic-automations',
            'asset' => 'statamic/cms',
            'asset_container' => 'statamic/cms',
            'collection' => 'statamic/cms',
            'entry' => 'statamic/cms',
            'form' => 'statamic/cms',
            'global' => 'statamic/cms',
            'revision' => 'statamic/cms',
            'site' => 'statamic/cms',
            'taxonomy' => 'statamic/cms',
            'term' => 'statamic/cms',
        ];

        $collisions = [];

        foreach ($this->routeParameters() as $parameter => $files) {
            if (isset($boundElsewhere[$parameter])) {
                $collisions[] = sprintf(
                    '{%s} in routes/%s is bound application-wide by %s',
                    $parameter,
                    implode(', routes/', $files),
                    $boundElsewhere[$parameter]
                );
            }
        }

        $this->assertSame([], $collisions, implode("\n", $collisions));
    }

    public function test_it_records_every_generic_route_parameter_as_a_deliberate_choice(): void
    {
        // Names generic enough that a sibling addon could plausibly claim one
        // tomorrow. The four this addon binds are on the list on purpose: they
        // are the reason the LeadHub defect existed, and they should never be
        // joined by a fifth without somebody deciding to.
        $generic = [
            'id', 'handle', 'slug', 'name', 'key', 'type', 'item', 'action',
            'user', 'group', 'role', 'status', 'field', 'page', 'token', 'tag',
            'list', 'record', 'model', 'source', 'preset', 'run', 'rule',
            'template', 'webhook', 'endpoint', 'automation',
        ];

        // Already shipped and bound by this addon, so a sibling using any of
        // them loses — see the class docblock. Renaming them here would not
        // change a single URL and would not remove that exposure, because the
        // binding names would still have to be claimed somewhere.
        $accepted = [
            'webhook',   // bound: outbound webhook, via OutboundWebhookRepository
            'endpoint',  // bound: inbound endpoint, via InboundEndpointRepository
            'rule',      // bound: routing rule, via RuleRepository
            'template',  // bound: payload template, via TemplateRepository
            'preset',    // integration preset handle, a plain string
            'handle',    // public inbound URL segment, /!/webhooks/inbound/{handle}
        ];

        $unrecorded = array_values(array_diff(
            array_intersect(array_keys($this->routeParameters()), $generic),
            $accepted
        ));

        $this->assertSame([], $unrecorded, sprintf(
            'New generic route parameter(s): %s. Either give them a name no '
            .'sibling addon would pick (the {scoringRule} pattern), or add them '
            .'to $accepted here with the reason.',
            implode(', ', $unrecorded)
        ));
    }
}
