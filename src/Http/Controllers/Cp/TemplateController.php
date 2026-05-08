<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Domain\Template\Actions\CreateTemplateAction;
use Goldnead\WebhookManager\Domain\Template\Actions\DeleteTemplateAction;
use Goldnead\WebhookManager\Domain\Template\Actions\UpdateTemplateAction;
use Goldnead\WebhookManager\Domain\Template\Models\Template;
use Goldnead\WebhookManager\Http\Requests\SaveTemplateRequest;
use Goldnead\WebhookManager\Registries\VariableResolverRegistry;
use Goldnead\WebhookManager\Repositories\TemplateRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Http\Controllers\CP\CpController;

class TemplateController extends CpController
{
    public function index(
        Request $request,
        TemplateRepository $repository,
    ) {
        $this->authorizeAny($request, 'manage webhook templates', 'view webhooks');

        $type = $request->get('type');
        $type = in_array($type, ['outbound_body', 'inbound_response', 'notification'], true) ? $type : null;

        $templates = $repository->paginate(25, $request->get('q'), $type);
        $rows = $templates->getCollection()->map(fn (Template $t) => $this->row($t));

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $rows,
                'meta' => [
                    'current_page' => $templates->currentPage(),
                    'last_page' => $templates->lastPage(),
                    'per_page' => $templates->perPage(),
                    'total' => $templates->total(),
                ],
            ]);
        }

        return Inertia::render('webhook-manager::Templates/Index', [
            'templates' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $templates->currentPage(),
                    'last_page' => $templates->lastPage(),
                    'per_page' => $templates->perPage(),
                    'total' => $templates->total(),
                ],
            ],
            'createUrl' => cp_route('webhook-manager.templates.create'),
            'canCreate' => (bool) $request->user()?->can('manage webhook templates'),
            'searchTerm' => $request->get('q', ''),
            'typeFilter' => $type ?? '',
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function create(Request $request, VariableResolverRegistry $vars)
    {
        $this->authorizeOr403($request, 'manage webhook templates');

        $template = new Template([
            'type' => Template::TYPE_OUTBOUND_BODY,
            'body' => '',
        ]);

        return Inertia::render('webhook-manager::Templates/Edit', [
            'template' => $this->editPayload($template),
            'typeOptions' => $this->typeOptions(),
            'namespaces' => array_keys($vars->all()),
            'isNew' => true,
            'saveUrl' => cp_route('webhook-manager.templates.store'),
            'previewUrl' => cp_route('webhook-manager.actions.preview-template'),
            'indexUrl' => cp_route('webhook-manager.templates.index'),
        ]);
    }

    public function store(SaveTemplateRequest $request, CreateTemplateAction $create)
    {
        $this->authorizeOr403($request, 'manage webhook templates');

        $template = ($create)($request->validated());

        return redirect(cp_route('webhook-manager.templates.edit', $template))
            ->with('success', __('webhook-manager::messages.template_created'));
    }

    public function edit(
        Request $request,
        Template $template,
        VariableResolverRegistry $vars,
    ) {
        $this->authorizeOr403($request, 'manage webhook templates');

        return Inertia::render('webhook-manager::Templates/Edit', [
            'template' => $this->editPayload($template),
            'typeOptions' => $this->typeOptions(),
            'namespaces' => array_keys($vars->all()),
            'isNew' => false,
            'saveUrl' => cp_route('webhook-manager.templates.update', $template),
            'deleteUrl' => cp_route('webhook-manager.templates.destroy', $template),
            'previewUrl' => cp_route('webhook-manager.actions.preview-template'),
            'indexUrl' => cp_route('webhook-manager.templates.index'),
        ]);
    }

    public function update(SaveTemplateRequest $request, Template $template, UpdateTemplateAction $update)
    {
        $this->authorizeOr403($request, 'manage webhook templates');

        ($update)($template, $request->validated());

        return back()->with('success', __('webhook-manager::messages.template_updated'));
    }

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

    /** @return array<string,mixed> */
    protected function row(Template $template): array
    {
        return [
            'id' => $template->id,
            'uuid' => $template->uuid,
            'name' => $template->name,
            'handle' => $template->handle,
            'type' => $template->type,
            'edit_url' => cp_route('webhook-manager.templates.edit', $template),
            'delete_url' => cp_route('webhook-manager.templates.destroy', $template),
        ];
    }

    /** @return array<string,mixed> */
    protected function editPayload(Template $template): array
    {
        return [
            'id' => $template->id,
            'uuid' => $template->uuid,
            'name' => $template->name,
            'handle' => $template->handle,
            'type' => $template->type ?? Template::TYPE_OUTBOUND_BODY,
            'body' => $template->body ?? '',
            'meta' => $template->meta ?? null,
        ];
    }

    /** @return array<string,string> */
    protected function typeOptions(): array
    {
        return [
            Template::TYPE_OUTBOUND_BODY => __('Outbound request body'),
            Template::TYPE_INBOUND_RESPONSE => __('Inbound response body'),
            Template::TYPE_NOTIFICATION => __('Notification body'),
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
