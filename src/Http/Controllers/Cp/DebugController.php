<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Goldnead\WebhookManager\Registries\VariableResolverRegistry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Http\Controllers\CP\CpController;

class DebugController extends CpController
{
    public function index(Request $request, TriggerRegistry $triggers, VariableResolverRegistry $resolvers)
    {
        abort_unless($request->user()?->can('use webhook debug tools'), 403);

        $triggersData = collect($triggers->all())->map(fn ($t) => [
            'handle' => $t->handle(),
            'label' => $t->label(),
            'source_type' => $t->sourceType(),
        ])->values();

        $resolversData = collect($resolvers->all())->map(fn ($r) => [
            'namespace' => $r->namespace(),
        ])->values();

        return Inertia::render('webhook-manager::Debug/Index', [
            'triggers' => $triggersData,
            'resolvers' => $resolversData,
            'previewUrl' => cp_route('webhook-manager.actions.preview-template'),
            'simulateUrl' => cp_route('webhook-manager.actions.simulate-trigger'),
        ]);
    }
}
