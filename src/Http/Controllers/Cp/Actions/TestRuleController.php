<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp\Actions;

use Goldnead\WebhookManager\Domain\Rule\Actions\TestRuleAction;
use Goldnead\WebhookManager\Domain\Rule\Models\Rule;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * CP-side "Test rule" button. Runs the rule's condition + action layer
 * against a synthetic trigger payload supplied by the editor.
 *
 * Authorisation comes from CP permissions; a user must hold
 * `manage webhook rules` to fire test executions.
 *
 * Note: actions execute for real (entries are created, webhooks are sent)
 * — this matches the outbound Test action's semantics.
 */
class TestRuleController extends Controller
{
    public function __invoke(
        Request $request,
        Rule $rule,
        TestRuleAction $test,
    ) {
        abort_unless($request->user()?->can('manage webhook rules'), 403);

        $payload = (array) $request->input('sample_payload', []);
        $site = $request->input('site');

        $result = ($test)($rule, $payload, is_string($site) ? $site : null);

        return response()->json([
            'ok' => $result->ok,
            'message' => $result->message,
            'data' => $result->data,
        ]);
    }
}
