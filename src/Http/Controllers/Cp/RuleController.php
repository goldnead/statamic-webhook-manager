<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Domain\Rule\Actions\CreateRuleAction;
use Goldnead\WebhookManager\Domain\Rule\Actions\DeleteRuleAction;
use Goldnead\WebhookManager\Domain\Rule\Actions\ToggleRuleAction;
use Goldnead\WebhookManager\Domain\Rule\Actions\UpdateRuleAction;
use Goldnead\WebhookManager\Domain\Rule\Models\Rule;
use Goldnead\WebhookManager\Http\Requests\SaveRuleRequest;
use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Goldnead\WebhookManager\Contracts\Repositories\RuleRepositoryInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Http\Controllers\CP\CpController;

class RuleController extends CpController
{
    public function index(
        Request $request,
        RuleRepositoryInterface $repository,
        TriggerRegistry $triggers,
    ) {
        $this->authorizeAny($request, 'manage webhook rules', 'view webhooks');

        // <Listing> sends `search`, `sort`, `order`, `page`, `perPage`.
        // We also accept the legacy `q` param to keep older bookmarks working.
        $perPage = (int) $request->get('perPage', 25) ?: 25;
        $search  = $request->get('search', $request->get('q', ''));

        $rules = $repository->paginate($perPage, $search);
        $triggerLabels = $triggers->options();

        $rows = $rules->getCollection()
            ->map(fn (Rule $r) => $this->row($r, $request, $triggerLabels))
            ->values();

        $listingPayload = [
            'data' => $rows,
            'meta' => [
                'current_page' => $rules->currentPage(),
                'last_page'    => $rules->lastPage(),
                'per_page'     => $rules->perPage(),
                'total'        => $rules->total(),
                'from'         => $rules->firstItem(),
                'to'           => $rules->lastItem(),
            ],
        ];

        // <Listing> issues an AJAX GET on search/sort/page changes; JSON
        // response avoids a full Inertia round-trip.
        if ($request->wantsJson()) {
            return response()->json($listingPayload);
        }

        return Inertia::render('webhook-manager::Rules/Index', [
            'rules'          => $listingPayload,
            'initialColumns' => $this->indexColumns(),
            'listingUrl'     => cp_route('webhook-manager.rules.index'),
            'actionUrl'      => cp_route('webhook-manager.rules.index'),
            'createUrl'      => cp_route('webhook-manager.rules.create'),
            'canCreate'      => (bool) $request->user()?->can('manage webhook rules'),
            'searchTerm'     => $search,
            'triggerOptions' => $triggerLabels,
        ]);
    }

    public function create(
        Request $request,
        TriggerRegistry $triggers,
    ) {
        $this->authorizeOr403($request, 'manage webhook rules');

        $rule = new Rule([
            'enabled'         => true,
            'stop_on_failure' => false,
            'order_index'     => 0,
            'actions'         => [],
        ]);

        return Inertia::render('webhook-manager::Rules/Edit', [
            'rule'           => $this->editPayload($rule),
            'triggerOptions' => $triggers->options(),
            'actionOptions'  => [],
            'isNew'          => true,
            'canDelete'      => false,
            'canTest'        => false,
            'saveUrl'        => cp_route('webhook-manager.rules.store'),
            'indexUrl'       => cp_route('webhook-manager.rules.index'),
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
    ) {
        $this->authorizeOr403($request, 'manage webhook rules');

        $user = $request->user();

        return Inertia::render('webhook-manager::Rules/Edit', [
            'rule'           => $this->editPayload($rule),
            'triggerOptions' => $triggers->options(),
            'actionOptions'  => [],
            'isNew'          => false,
            'canDelete'      => (bool) $user?->can('manage webhook rules'),
            'canTest'        => (bool) $user?->can('manage webhook rules'),
            'saveUrl'        => cp_route('webhook-manager.rules.update', $rule),
            'deleteUrl'      => cp_route('webhook-manager.rules.destroy', $rule),
            'toggleUrl'      => cp_route('webhook-manager.rules.toggle', $rule),
            'testUrl'        => cp_route('webhook-manager.actions.test-rule', $rule),
            'indexUrl'       => cp_route('webhook-manager.rules.index'),
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

    /**
     * Column definitions for the <Listing> component on the index page.
     *
     * @return array<int,array{field:string,label:string,sortable?:bool,visible?:bool}>
     */
    protected function indexColumns(): array
    {
        return [
            ['field' => 'name',         'label' => __('Name'),         'sortable' => true,  'visible' => true],
            ['field' => 'trigger_type', 'label' => __('Trigger'),      'sortable' => true,  'visible' => true],
            ['field' => 'action_count', 'label' => __('Actions'),      'sortable' => false, 'visible' => true],
            ['field' => 'order_index',  'label' => __('Order'),        'sortable' => true,  'visible' => true],
            ['field' => 'enabled',      'label' => __('Status'),       'sortable' => true,  'visible' => true],
        ];
    }

    /**
     * Single-row payload for the listing. Includes pre-computed
     * permission flags and helper URLs so the Vue page never has to
     * check abilities or build routes itself.
     *
     * @param  array<string,string>  $triggerLabels
     * @return array<string,mixed>
     */
    protected function row(Rule $rule, Request $request, array $triggerLabels): array
    {
        $canManage = (bool) $request->user()?->can('manage webhook rules');

        return [
            'id'            => $rule->id,
            'uuid'          => $rule->uuid,
            'name'          => $rule->name,
            'handle'        => $rule->handle,
            'trigger_type'  => $rule->trigger_type,
            'trigger_label' => $triggerLabels[$rule->trigger_type] ?? $rule->trigger_type,
            'enabled'       => (bool) $rule->enabled,
            'action_count'  => is_array($rule->actions) ? count($rule->actions) : 0,
            'order_index'   => (int) $rule->order_index,

            // Permissions surfaced to the UI so v-if stays declarative.
            'can_edit'      => $canManage,
            'can_toggle'    => $canManage,
            'can_delete'    => $canManage,

            'edit_url'      => cp_route('webhook-manager.rules.edit', $rule),
            'toggle_url'    => cp_route('webhook-manager.rules.toggle', $rule),
            'delete_url'    => cp_route('webhook-manager.rules.destroy', $rule),
        ];
    }

    /**
     * Full rule payload for the edit/create form.
     *
     * @return array<string,mixed>
     */
    protected function editPayload(Rule $rule): array
    {
        return [
            'id'              => $rule->id,
            'uuid'            => $rule->uuid,
            'name'            => $rule->name,
            'handle'          => $rule->handle,
            'description'     => $rule->description ?? null,
            'enabled'         => (bool) ($rule->enabled ?? true),
            'trigger_type'    => $rule->trigger_type,
            'trigger_config'  => $rule->trigger_config ?? null,
            'conditions'      => $rule->conditions ?? null,
            'actions'         => $rule->actions ?? [],
            'stop_on_failure' => (bool) ($rule->stop_on_failure ?? false),
            'order_index'     => (int) ($rule->order_index ?? 0),
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
