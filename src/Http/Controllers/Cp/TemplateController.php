<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Domain\Template\Actions\CreateTemplateAction;
use Goldnead\WebhookManager\Domain\Template\Actions\DeleteTemplateAction;
use Goldnead\WebhookManager\Domain\Template\Actions\UpdateTemplateAction;
use Goldnead\WebhookManager\Domain\Template\Models\Template;
use Goldnead\WebhookManager\Http\Requests\SaveTemplateRequest;
use Goldnead\WebhookManager\Registries\VariableResolverRegistry;
use Goldnead\WebhookManager\Contracts\Repositories\TemplateRepositoryInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Http\Controllers\CP\CpController;

class TemplateController extends CpController
{
    // ──────────────────────────────────────────────────────────────────
    // Listing
    // ──────────────────────────────────────────────────────────────────

    public function index(Request $request, TemplateRepositoryInterface $repository)
    {
        $this->authorizeAny($request, 'manage webhook templates', 'view webhooks');

        // Statamic Listing sends `search` / `perPage`; accept legacy `q` too.
        $perPage = (int) $request->get('perPage', 25) ?: 25;
        $search  = $request->get('search', $request->get('q', ''));

        $type = $request->get('type');
        $type = in_array($type, ['outbound_body', 'inbound_response', 'notification'], true)
            ? $type
            : null;

        $templates = $repository->paginate($perPage, $search, $type);

        $rows = $templates->getCollection()
            ->map(fn (Template $t) => $this->row($t, $request))
            ->values();

        $listingPayload = [
            'data' => $rows,
            'meta' => [
                'current_page' => $templates->currentPage(),
                'last_page'    => $templates->lastPage(),
                'per_page'     => $templates->perPage(),
                'total'        => $templates->total(),
                'from'         => $templates->firstItem(),
                'to'           => $templates->lastItem(),
            ],
        ];

        // Statamic's Listing component issues AJAX GETs when search /
        // sort / page changes — returning JSON avoids a full page reload.
        if ($request->wantsJson()) {
            return response()->json($listingPayload);
        }

        return Inertia::render('webhook-manager::Templates/Index', [
            'templates'      => $listingPayload,
            'initialColumns' => $this->indexColumns(),
            'listingUrl'     => cp_route('webhook-manager.templates.index'),
            'actionUrl'      => cp_route('webhook-manager.templates.index'),
            'createUrl'      => cp_route('webhook-manager.templates.create'),
            'canCreate'      => (bool) $request->user()?->can('manage webhook templates'),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // Create / Store
    // ──────────────────────────────────────────────────────────────────

    public function create(Request $request, VariableResolverRegistry $vars)
    {
        $this->authorizeOr403($request, 'manage webhook templates');

        $template = new Template([
            'type' => Template::TYPE_OUTBOUND_BODY,
            'body' => '',
        ]);

        return Inertia::render('webhook-manager::Templates/Edit', [
            'template'    => $this->editPayload($template),
            'typeOptions' => $this->typeOptions(),
            'namespaces'  => array_keys($vars->all()),
            'isNew'       => true,
            'canDelete'   => false,
            'saveUrl'     => cp_route('webhook-manager.templates.store'),
            'previewUrl'  => cp_route('webhook-manager.actions.preview-template'),
            'indexUrl'    => cp_route('webhook-manager.templates.index'),
        ]);
    }

    public function store(SaveTemplateRequest $request, CreateTemplateAction $create)
    {
        $this->authorizeOr403($request, 'manage webhook templates');

        $template = ($create)($request->validated());

        return redirect(cp_route('webhook-manager.templates.edit', $template))
            ->with('success', __('webhook-manager::messages.template_created'));
    }

    // ──────────────────────────────────────────────────────────────────
    // Edit / Update
    // ──────────────────────────────────────────────────────────────────

    public function edit(
        Request $request,
        Template $template,
        VariableResolverRegistry $vars,
    ) {
        $this->authorizeOr403($request, 'manage webhook templates');

        return Inertia::render('webhook-manager::Templates/Edit', [
            'template'    => $this->editPayload($template),
            'typeOptions' => $this->typeOptions(),
            'namespaces'  => array_keys($vars->all()),
            'isNew'       => false,
            'canDelete'   => (bool) $request->user()?->can('manage webhook templates'),
            'saveUrl'     => cp_route('webhook-manager.templates.update', $template),
            'deleteUrl'   => cp_route('webhook-manager.templates.destroy', $template),
            'previewUrl'  => cp_route('webhook-manager.actions.preview-template'),
            'indexUrl'    => cp_route('webhook-manager.templates.index'),
        ]);
    }

    public function update(SaveTemplateRequest $request, Template $template, UpdateTemplateAction $update)
    {
        $this->authorizeOr403($request, 'manage webhook templates');

        ($update)($template, $request->validated());

        return back()->with('success', __('webhook-manager::messages.template_updated'));
    }

    // ──────────────────────────────────────────────────────────────────
    // Destroy
    // ──────────────────────────────────────────────────────────────────

    public function destroy(Request $request, Template $template, DeleteTemplateAction $delete)
    {
        $this->authorizeOr403($request, 'manage webhook templates');

        $result = ($delete)($template);

        $message = $result['detached_outbounds'] > 0
            ? __('webhook-manager::messages.template_deleted_with_detach', ['count' => $result['detached_outbounds']])
            : __('webhook-manager::messages.template_deleted');

        return redirect(cp_route('webhook-manager.templates.index'))
            ->with('success', $message);
    }

    // ──────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────

    /**
     * Single row representation for the Listing component.
     *
     * All permission and URL fields are pre-computed here so the Vue
     * template stays logic-free — the same convention as OutboundController.
     *
     * @return array
     */
    protected function row(Template $template, Request $request): array
    {
        $canManage = (bool) $request->user()?->can('manage webhook templates');

        return [
            'id'         => $template->id,
            'uuid'       => $template->uuid,
            'name'       => $template->name,
            'handle'     => $template->handle,
            'type'       => $template->type,
            'type_label' => $this->typeOptions()[$template->type] ?? $template->type,
            'type_color' => $this->typeColor($template->type),
            'updated_at' => $template->updated_at?->toIso8601String(),
            'edit_url'   => cp_route('webhook-manager.templates.edit', $template),
            'delete_url' => cp_route('webhook-manager.templates.destroy', $template),
            'can_edit'   => $canManage,
            'can_delete' => $canManage,
            // Templates do not have a duplicate route yet; set to null
            // and the Vue template will suppress the Dropdown item.
            'duplicate_url' => null,
        ];
    }

    /**
     * Payload passed to the Edit/Create Inertia page.
     *
     * @return array
     */
    protected function editPayload(Template $template): array
    {
        return [
            'id'     => $template->id,
            'uuid'   => $template->uuid,
            'name'   => $template->name,
            'handle' => $template->handle,
            'type'   => $template->type ?? Template::TYPE_OUTBOUND_BODY,
            'body'   => $template->body ?? '',
            'meta'   => $template->meta ?? null,
        ];
    }

    /**
     * Column definitions for the Listing component.
     * PHP-side definitions keep column labels translatable and let
     * Statamic's Listing handle sorting / preferences automatically.
     *
     * @return array
     */
    protected function indexColumns(): array
    {
        return [
            ['field' => 'name',       'label' => __('Name'),    'visible' => true, 'sortable' => true],
            ['field' => 'handle',     'label' => __('Handle'),  'visible' => true, 'sortable' => true],
            ['field' => 'type',       'label' => __('Type'),    'visible' => true, 'sortable' => true],
            ['field' => 'updated_at', 'label' => __('Updated'), 'visible' => true, 'sortable' => true],
        ];
    }

    /**
     * Human-readable labels for the type Select and Badge.
     *
     * @return array
     */
    protected function typeOptions(): array
    {
        return [
            Template::TYPE_OUTBOUND_BODY      => __('Outbound request body'),
            Template::TYPE_INBOUND_RESPONSE   => __('Inbound response body'),
            Template::TYPE_NOTIFICATION       => __('Notification body'),
        ];
    }

    /**
     * Semantic Badge colours per type.
     * Kept here (not in Vue) so any server-rendered context (e.g. mail)
     * can also use the same mapping without duplication.
     */
    protected function typeColor(string $type): string
    {
        return match ($type) {
            Template::TYPE_OUTBOUND_BODY    => 'blue',
            Template::TYPE_NOTIFICATION     => 'amber',
            default                         => 'gray',
        };
    }

    // ──────────────────────────────────────────────────────────────────
    // Authorization helpers  (same pattern as OutboundController)
    // ──────────────────────────────────────────────────────────────────

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
