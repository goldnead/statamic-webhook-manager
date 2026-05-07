<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Domain\Rule\Models\Rule;
use Goldnead\WebhookManager\Repositories\RuleRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Http\Controllers\CP\CpController;

/**
 * TODO: REVIEW — listing only; rule engine + edit screens ship later.
 */
class RuleController extends CpController
{
    public function index(Request $request, RuleRepository $repository)
    {
        abort_unless($request->user()?->can('manage webhook rules'), 403);

        $rules = $repository->all()->map(fn (Rule $r) => [
            'id' => $r->id,
            'uuid' => $r->uuid,
            'name' => $r->name,
            'handle' => $r->handle,
            'trigger_type' => $r->trigger_type,
            'enabled' => (bool) $r->enabled,
        ])->values();

        return Inertia::render('webhook-manager::Rules/Index', [
            'rules' => $rules,
        ]);
    }
}
