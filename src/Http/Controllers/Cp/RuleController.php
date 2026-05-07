<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Repositories\RuleRepository;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * TODO: REVIEW — listing only; edit screens ship with the rule engine iteration.
 */
class RuleController extends Controller
{
    public function index(Request $request, RuleRepository $repository)
    {
        abort_unless($request->user()?->can('manage webhook rules'), 403);

        return view('webhook-manager::cp.rules.index', [
            'rules' => $repository->all(),
        ]);
    }
}
