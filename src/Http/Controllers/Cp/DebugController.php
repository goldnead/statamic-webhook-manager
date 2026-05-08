<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Goldnead\WebhookManager\Registries\VariableResolverRegistry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Http\Controllers\CP\CpController;

class DebugController extends CpController
{
    /**
     * Render the Debug utility page.
     *
     * Passes all registered triggers and resolver namespaces to the Vue layer
     * so users can inspect the runtime state of the Webhook Manager without
     * needing to read PHP logs or config files.
     *
     * The previewUrl and simulateUrl are forwarded only when the corresponding
     * routes exist (they are defined in routes/cp.php under the actions group).
     * If simulateUrl is null the "Simulate Trigger" panel is hidden in Vue.
     */
    public function index(Request $request, TriggerRegistry $triggers, VariableResolverRegistry $resolvers)
    {
        abort_unless(
            $request->user()?->can('use webhook debug tools'),
            403
        );

        $triggersData = collect($triggers->all())->map(fn ($t) => [
            'handle'      => $t->handle(),
            'label'       => $t->label(),
            'source_type' => $t->sourceType(),
            'description' => method_exists($t, 'description') ? $t->description() : null,
        ])->values();

        $resolversData = collect($resolvers->all())->map(fn ($r) => [
            'namespace' => $r->namespace(),
        ])->values();

        // Both action routes must exist; the Vue layer guards on simulateUrl
        // being non-null before rendering the "Simulate Trigger" panel.
        $previewUrl  = cp_route('webhook-manager.actions.preview-template');
        $simulateUrl = $this->routeExistsOrNull('webhook-manager.actions.simulate-trigger');

        return Inertia::render('webhook-manager::Debug/Index', [
            'triggers'    => $triggersData,
            'resolvers'   => $resolversData,
            'previewUrl'  => $previewUrl,
            'simulateUrl' => $simulateUrl,
        ]);
    }

    /**
     * Return the named CP route URL, or null if the route is not registered.
     *
     * Useful so we can conditionally expose action endpoints to the Vue layer
     * without throwing RouteNotFoundException when optional features are absent.
     */
    private function routeExistsOrNull(string $name): ?string
    {
        try {
            return cp_route($name);
        } catch (\Symfony\Component\Routing\Exception\RouteNotFoundException) {
            return null;
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
