<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Tests\CpTestCase;
use Goldnead\WebhookManager\WebhookManagerServiceProvider;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

/**
 * Route parameter names are one namespace shared by the whole application.
 *
 * `Route::bind('rule', …)` is registered on the router, not on the package that
 * calls it. From that moment every route named `{rule}` in every installed
 * addon resolves through this addon's rule repository — and the route that
 * loses does not fail loudly. It hands a foreign id to a repository that has
 * never heard of it and answers 404. goldnead/statamic-leadhub 1.8.0 shipped
 * `/scoring/{rule}` and lost its edit and its delete that way, through a
 * release, with no error anywhere and a green suite on both sides.
 *
 * ── The rule ──────────────────────────────────────────────────────────────
 *
 *   An addon may only bind parameter names that unambiguously belong to it.
 *   A bound name must be specific enough that no sibling package would reach
 *   for it by accident. Names that are NOT bound may stay generic — they
 *   cannot collide, because nothing resolves them.
 *
 * Here the shape of "belongs to it" is the `webhook` prefix plus a capital:
 * `webhookOutbound`, `webhookInbound`, `webhookRule`, `webhookTemplate`. The
 * URLs are byte-identical to the ones before the rename; only the placeholders
 * changed, and with them the `$this->route(…)` lookups in the FormRequests and
 * the controller signatures.
 *
 * ── Why this is checkable and the old snapshot list was not ───────────────
 *
 * The previous version of this file compared this addon's parameter names
 * against a hand-written list of names other installed packages bind. That
 * list can only describe the siblings as they are today: it says nothing about
 * the addon that starts binding `{handle}` next month, which is exactly the
 * case that hurts. The rule above needs no knowledge of the siblings at all.
 * It is a property of this package, so this package's own suite can enforce it.
 *
 * That is also what protects the generic names still in these route files.
 * `{handle}` (the public inbound URL segment) and `{preset}` (an integration
 * handle) are unbound and are staying unbound — renaming them would move text
 * without removing any exposure. They are safe as long as nobody binds them,
 * and under the rule above nobody may: a package that binds must bind a name
 * of its own, and `handle` is nobody's own. The same argument covers `{id}`,
 * which statamic-activity and statamic-notifications use unbound today.
 *
 * ── What this file still cannot do ────────────────────────────────────────
 *
 * A collision only exists once two packages are installed together, and a
 * package cannot see its siblings from inside its own suite. What the last
 * test below adds is the reverse direction for packages that do NOT follow our
 * rule — statamic/cms binds `{entry}`, `{collection}` and friends, and always
 * will. That list is short, third-party, and stable; it is not a stand-in for
 * knowing what the siblings do.
 */
class RouteParameterCollisionTest extends CpTestCase
{
    /**
     * Generic names a sibling addon could plausibly put in one of its own
     * routes. `rule` and `template` are not hypothetical — LeadHub shipped
     * `{rule}`. `handle` and `id` are the two the family shares unbound today.
     *
     * @var list<string>
     */
    private const NAMES_A_SIBLING_MIGHT_USE = [
        'rule', 'template', 'webhook', 'endpoint', 'handle', 'id', 'slug', 'record',
    ];

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
        // Re-registering `webhookRule` replaces the addon's own binder for this
        // test. SubstituteBindings runs as middleware, ahead of the controller,
        // so a 418 here proves the middleware ran — with it absent the request
        // reaches RuleController and answers something else entirely.
        Route::bind('webhookRule', function ($value) {
            abort(418, 'binding reached');
        });

        $this->get('/cp/webhook-manager/rules/does-not-exist')->assertStatus(418);
    }

    /**
     * The failure this whole file exists for, reproduced from the other side.
     *
     * These are not this addon's routes. They stand in for a sibling package's
     * routes, mounted with the same middleware a real CP install uses, and they
     * do nothing but echo their own parameter back. If this addon binds a name
     * they use, the echo never happens: the binder resolves the value against a
     * repository here first, finds nothing, and aborts 404 — which is precisely
     * what LeadHub's delete button did.
     *
     * Before the rename this failed on `rule`, `template`, `webhook` and
     * `endpoint` with 404. It is the test that had to fail first.
     */
    public function test_a_sibling_addons_generic_parameter_is_not_swallowed_by_this_addon(): void
    {
        foreach (self::NAMES_A_SIBLING_MIGHT_USE as $name) {
            Route::middleware(SubstituteBindings::class)
                ->get('sibling-probe/'.$name.'/{'.$name.'}', fn ($value) => (string) $value);
        }

        $swallowed = [];

        foreach (self::NAMES_A_SIBLING_MIGHT_USE as $name) {
            $response = $this->get('sibling-probe/'.$name.'/sibling-owned-id-42');

            if ($response->status() !== 200 || $response->getContent() !== 'sibling-owned-id-42') {
                $swallowed[] = sprintf(
                    '{%s}: a sibling route with this parameter answered %d instead of echoing its own value — '
                    .'this addon resolves the name application-wide and ate it',
                    $name,
                    $response->status()
                );
            }
        }

        $this->assertSame([], $swallowed, implode("\n", $swallowed));
    }

    /**
     * The rule itself, enforced against the one array that decides it.
     */
    public function test_every_parameter_this_addon_binds_is_owned_by_this_addon(): void
    {
        $generic = [];

        foreach (array_keys(WebhookManagerServiceProvider::routeModelBindings()) as $parameter) {
            if (! preg_match('/^webhook[A-Z][A-Za-z0-9]*$/', $parameter)) {
                $generic[] = sprintf('{%s} is bound application-wide but is not this addon\'s name', $parameter);
            }
        }

        $this->assertSame([], $generic, implode("\n", $generic)."\n"
            .'A Route::bind() reaches into every addon installed alongside. Bind only names '
            .'prefixed `webhook` + a capital (webhookRule, webhookTemplate, …) so no sibling '
            .'can pick one by accident. Generic names are fine as long as they stay UNBOUND.');
    }

    /**
     * The two halves have to agree: everything bound appears in the routes, and
     * everything in the routes that is generic is genuinely unbound.
     *
     * `{preset}` and `{handle}` are the generic ones that remain, deliberately.
     * `{delivery}` is generic-ish and also unbound here — it resolves through
     * Laravel's *implicit* binding, which matches a route parameter to a typed
     * controller argument and is therefore scoped to that one route. Only
     * `Route::bind()` is application-wide, which is why only that array is the
     * subject of the rule.
     */
    public function test_the_bound_names_and_the_route_files_agree(): void
    {
        $bound = array_keys(WebhookManagerServiceProvider::routeModelBindings());
        $declared = array_keys($this->routeParameters());

        $this->assertSame([], array_values(array_diff($bound, $declared)),
            'Bound but not used by any route in this addon — a binding with no route of its own is '
            .'pure exposure for the siblings, delete it.');

        $unbound = array_values(array_diff($declared, $bound));
        sort($unbound);

        $this->assertSame(
            ['delivery', 'handle', 'preset'],
            $unbound,
            'The unbound parameter names changed. Unbound is where generic names are allowed to '
            .'live: nothing resolves them, so nothing can be taken from anyone. Keep it that way — '
            .'if one of these ever needs a binding, rename it to `webhook…` in the same commit.'
        );
    }

    /**
     * The reverse direction, for packages that do not follow our rule.
     *
     * statamic/cms binds the CMS entity names application-wide and always will;
     * a route of ours called `{entry}` would lose in exactly the way LeadHub's
     * did. Sibling addons are deliberately NOT on this list any more — the rule
     * enforced above is what covers them, and a hand-kept snapshot of what the
     * siblings bind today is the thing that failed to see LeadHub coming.
     */
    public function test_it_uses_no_route_parameter_a_third_party_package_binds(): void
    {
        $boundByStatamic = [
            'asset', 'asset_container', 'collection', 'entry', 'form',
            'global', 'revision', 'site', 'taxonomy', 'term',
        ];

        $collisions = [];

        foreach ($this->routeParameters() as $parameter => $files) {
            if (in_array($parameter, $boundByStatamic, true)) {
                $collisions[] = sprintf(
                    '{%s} in routes/%s is bound application-wide by statamic/cms',
                    $parameter,
                    implode(', routes/', $files)
                );
            }
        }

        $this->assertSame([], $collisions, implode("\n", $collisions));
    }
}
