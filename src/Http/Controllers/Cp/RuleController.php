<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Domain\Rule\Actions\CreateRuleAction;
use Goldnead\WebhookManager\Domain\Rule\Actions\DeleteRuleAction;
use Goldnead\WebhookManager\Domain\Rule\Actions\ToggleRuleAction;
use Goldnead\WebhookManager\Domain\Rule\Actions\UpdateRuleAction;
use Goldnead\WebhookManager\Domain\Rule\Models\Rule;
use Goldnead\WebhookManager\Http\Requests\SaveRuleRequest;
use Goldnead\WebhookManager\Registries\ActionRegistry;
use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Goldnead\WebhookManager\Repositories\RuleRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Http\Controllers\CP\CpController;

class RuleController extends CpController
{
    public function index(
        Request $request,
        RuleRepository $repository,
        TriggerRegistry $triggers,
        ActionRegistry $actions,
    ) {
        $this->authorizeAny($request, 'manage webhook rules', 'view webhooks');

        $rules = $repository->paginate(25, $request->get('q'));
        $rows = $rules->getCollection()->map(fn (Rule $r) => $this->row($r));

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $rows,
                'meta' => [
                    'current_page' => $rules->currentPage(),
                    'last_page' => $rules->lastPage(),
                    'per_page' => $rules->perPage(),
                    'total' => $rules->total(),
                ],
            ]);
        }

        return Inertia::render('webhook-manager::Rules/Index', [
            'rules' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $rules->currentPage(),
                    'last_page' => $rules->lastPage(),
                    'per_page' => $rules->perPage(),
                    'total' => $rules->total(),
                ],
            ],
            'createUrl' => cp_route('webhook-manager.rules.create'),
            'canCreate' => (bool) $request->user()?->can('manage webhook rules'),
            'searchTerm' => $request->get('q', ''),
            'triggerOptions' => $triggers->options(),
            'actionOptions' => $actions->options(),
        ]);
    }

    public function create(
        Request $request,
        TriggerRegistry $triggers,
        ActionRegistry $actions,
    ) {
        $this->authorizeOr403($request, 'manage webhook rules');

        $rule = new Rule([
            'enabled' => true,
            'stop_on_failure' => false,
            'order_index' => 0,
            'actions' => [],
        ]);

        return Inertia::render('webhook-manager::Rules/Edit', [
            'rule' => $this->editPayload($rule),
            'triggerOptions' => $triggers->options(),
            'actionOptions' => $actions->options(),
            'isNew' => true,
            'saveUrl' => cp_route('webhook-manager.rules.store'),
            'indexUrl' => cp_route('webhook-manager.rules.index'),
        ]);
    }

    public function store(SaveRuleRequest $request, CreateRuleAction $create)
    {
        $this->authorizeOr403($request, 'manage webhook rules');

        $rule = ($create)($request->validated());

        return redirect(cp_route('webhook-manager.rules.edit', $rule))
            ->with('success', __('webhook-manager::messages.rule_created'));
    }

    public function edit(
        Request $request,
        Rule $rule,
        TriggerRegistry $triggers,
        ActionRegistry $actions,
    ) {
        $this->authorizeOr403($request, 'manage webhook rules');

        return Inertia::render('webhook-manager::Rules/Edit', [
            'rule' => $this->editPayload($rule),
            'triggerOptions' => $triggers->options(),
            'actionOptions' => $actions->options(),
            'isNew' => false,
            'saveUrl' => cp_route('webhook-manager.rules.update', $rule),
            'deleteUrl' => cp_route('webhook-manager.rules.destroy', $rule),
            'toggleUrl' => cp_route('webhook-manager.rules.toggle', $rule),
            'testUrl' => cp_route('webhook-manager.actions.test-rule', $rule),
            'indexUrl' => cp_route('webhook-manager.rules.index'),
        ]);
    }

    public function update(SaveRuleRequest $request, Rule $rule, UpdateRuleAction $update)
    {
        $this->authorizeOr403($request, 'manage webhook rules');

        ($update)($rule, $request->validated());

        return back()->with('success', __('webhook-manager::messages.rule_updated'));
    }

    public function destroy(Request $request, Rule $rule, DeleteRuleAction $delete)
    {
        $this->authorizeOr403($request, 'manage webhook rules');

        ($delete)($rule);

        return redirect(cp_route('webhook-manager.rules.index'))
            ->with('success', __('webhook-manager::messages.rule_deleted'));
    }

    public function toggle(Request $request, Rule $rule, ToggleRuleAction $toggle)
    {
        $this->authorizeOr403($request, 'manage webhook rules');

        $rule = ($toggle)($rule);

        return back()->with('success', $rule->enabled
            ? __('webhook-manager::messages.rule_enabled')
            : __('webhook-manager::messages.rule_disabled'));
    }

    /** @return array<string,mixed> */
    protected function row(Rule $rule): array
    {
        return [
            'id' => $rule->id,
            'uuid' => $rule->uuid,
            'name' => $rule->name,
            'handle' => $rule->handle,
            'trigger_type' => $rule->trigger_type,
            'enabled' => (bool) $rule->enabled,
            'action_count' => is_array($rule->actions) ? count($rule->actions) : 0,
            'order_index' => (int) $rule->order_index,
            'edit_url' => cp_route('webhook-manager.rules.edit', $rule),
            'toggle_url' => cp_route('webhook-manager.rules.toggle', $rule),
            'delete_url' => cp_route('webhook-manager.rules.destroy', $rule),
        ];
    }

    /** @return array<string,mixed> */
    protected function editPayload(Rule $rule): array
    {
        return [
            'id' => $rule->id,
            'uuid' => $rule->uuid,
            'name' => $rule->name,
            'handle' => $rule->handle,
            'enabled' => (bool) ($rule->enabled ?? true),
            'trigger_type' => $rule->trigger_type,
            'trigger_config' => $rule->trigger_config ?? null,
            'conditions' => $rule->conditions ?? null,
            'actions' => $rule->actions ?? [],
            'stop_on_failure' => (bool) ($rule->stop_on_failure ?? false),
            'order_index' => (int) ($rule->order_index ?? 0),
        ];
    }

    private function authorizeOr403(Request $request, string $ability): void
    {
        abort_unless($request->user()?->can($ability), 403);
    }

    private function authorizeAny(Request $request, string ...$abilities): void
    {
        $user = $request->user();
        foreach ($abilities as $ability) {
            if ($user?->can($ability)) {
                return;
            }
        }
        abort(403);
    }
}
