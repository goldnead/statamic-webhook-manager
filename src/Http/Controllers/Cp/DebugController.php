<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Goldnead\WebhookManager\Registries\VariableResolverRegistry;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DebugController extends Controller
{
    public function index(Request $request, TriggerRegistry $triggers, VariableResolverRegistry $resolvers)
    {
        abort_unless($request->user()?->can('use webhook debug tools'), 403);

        return view('webhook-manager::cp.debug.index', [
            'triggers' => $triggers->all(),
            'resolvers' => $resolvers->all(),
        ]);
    }
}
