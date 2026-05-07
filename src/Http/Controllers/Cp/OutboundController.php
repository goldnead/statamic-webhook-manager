<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Domain\OutboundWebhook\Actions\CreateOutboundWebhookAction;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Actions\DeleteOutboundWebhookAction;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Actions\ToggleOutboundWebhookAction;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Actions\UpdateOutboundWebhookAction;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Http\Requests\SaveOutboundWebhookRequest;
use Goldnead\WebhookManager\Registries\AuthSchemeRegistry;
use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Goldnead\WebhookManager\Repositories\OutboundWebhookRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Http\Controllers\CP\CpController;

class OutboundController extends CpController
{
    public function index(
        Request $request,
        OutboundWebhookRepository $repository,
        TriggerRegistry $triggers,
    ) {
        $this->authorizeAny($request, 'manage outbound webhooks', 'view webhooks');

        $hooks = $repository->paginate(25, $request->get('q'));
        $rows = $hooks->getCollection()->map(fn (OutboundWebhook $hook) => $this->row($hook));

        // AJAX re-fetches from <Listing> use the same endpoint
        if ($request->wantsJson()) {
            return response()->json([
                'data' => $rows,
                'meta' => [
                    'current_page' => $hooks->currentPage(),
                    'last_page' => $hooks->lastPage(),
                    'per_page' => $hooks->perPage(),
                    'total' => $hooks->total(),
                ],
            ]);
        }

        return Inertia::render('webhook-manager::Outbound/Index', [
            'webhooks' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $hooks->currentPage(),
                    'last_page' => $hooks->lastPage(),
                    'per_page' => $hooks->perPage(),
                    'total' => $hooks->total(),
                ],
            ],
            'createUrl' => cp_route('webhook-manager.outbound.create'),
            'canCreate' => (bool) $request->user()?->can('manage outbound webhooks'),
            'searchTerm' => $request->get('q', ''),
            'triggerOptions' => $triggers->options(),
        ]);
    }

    public function create(Request $request, TriggerRegistry $triggers, AuthSchemeRegistry $auth)
    {
        $this->authorizeOr403($request, 'manage outbound webhooks');

        $hook = new OutboundWebhook([
            'method' => 'POST',
            'enabled' => true,
            'queue_enabled' => true,
            'auth_type' => 'none',
            'payload_type' => 'raw_json',
            'timeout_seconds' => 15,
            'follow_redirects' => true,
            'log_body_mode' => 'partial',
        ]);

        return Inertia::render('webhook-manager::Outbound/Edit', [
            'webhook' => $this->editPayload($hook),
            'triggerOptions' => $triggers->options(),
            'authOptions' => $auth->options(),
            'isNew' => true,
            'saveUrl' => cp_route('webhook-manager.outbound.store'),
            'indexUrl' => cp_route('webhook-manager.outbound.index'),
        ]);
    }

    public function store(SaveOutboundWebhookRequest $request, CreateOutboundWebhookAction $create)
    {
        $this->authorizeOr403($request, 'manage outbound webhooks');

        $attributes = $this->normalizeAuthConfig($request->validated());
        $hook = ($create)($attributes);

        return redirect(cp_route('webhook-manager.outbound.edit', $hook))
            ->with('success', __('webhook-manager::messages.created'));
    }

    public function edit(
        Request $request,
        OutboundWebhook $webhook,
        TriggerRegistry $triggers,
        AuthSchemeRegistry $auth,
    ) {
        $this->authorizeOr403($request, 'manage outbound webhooks');

        return Inertia::render('webhook-manager::Outbound/Edit', [
            'webhook' => $this->editPayload($webhook),
            'triggerOptions' => $triggers->options(),
            'authOptions' => $auth->options(),
            'isNew' => false,
            'saveUrl' => cp_route('webhook-manager.outbound.update', $webhook),
            'deleteUrl' => cp_route('webhook-manager.outbound.destroy', $webhook),
            'toggleUrl' => cp_route('webhook-manager.outbound.toggle', $webhook),
            'testUrl' => cp_route('webhook-manager.actions.test-outbound', $webhook),
            'indexUrl' => cp_route('webhook-manager.outbound.index'),
        ]);
    }

    public function update(
        SaveOutboundWebhookRequest $request,
        OutboundWebhook $webhook,
        UpdateOutboundWebhookAction $update,
    ) {
        $this->authorizeOr403($request, 'manage outbound webhooks');

        $attributes = $this->normalizeAuthConfig($request->validated());
        ($update)($webhook, $attributes);

        return back()->with('success', __('webhook-manager::messages.updated'));
    }

    public function destroy(Request $request, OutboundWebhook $webhook, DeleteOutboundWebhookAction $delete)
    {
        $this->authorizeOr403($request, 'manage outbound webhooks');

        ($delete)($webhook);

        return redirect(cp_route('webhook-manager.outbound.index'))
            ->with('success', __('webhook-manager::messages.deleted'));
    }

    public function toggle(Request $request, OutboundWebhook $webhook, ToggleOutboundWebhookAction $toggle)
    {
        $this->authorizeOr403($request, 'manage outbound webhooks');

        $webhook = ($toggle)($webhook);

        return back()->with('success', $webhook->enabled
            ? __('webhook-manager::messages.enabled')
            : __('webhook-manager::messages.disabled'));
    }

    /** @return array<string,mixed> */
    protected function row(OutboundWebhook $hook): array
    {
        return [
            'id' => $hook->id,
            'uuid' => $hook->uuid,
            'name' => $hook->name,
            'handle' => $hook->handle,
            'trigger_type' => $hook->trigger_type,
            'url' => $hook->url,
            'method' => $hook->method,
            'enabled' => (bool) $hook->enabled,
            'edit_url' => cp_route('webhook-manager.outbound.edit', $hook),
            'toggle_url' => cp_route('webhook-manager.outbound.toggle', $hook),
            'delete_url' => cp_route('webhook-manager.outbound.destroy', $hook),
        ];
    }

    /** @return array<string,mixed> */
    protected function editPayload(OutboundWebhook $hook): array
    {
        return [
            'id' => $hook->id,
            'uuid' => $hook->uuid,
            'name' => $hook->name,
            'handle' => $hook->handle,
            'description' => $hook->description,
            'enabled' => (bool) ($hook->enabled ?? true),
            'trigger_type' => $hook->trigger_type,
            'trigger_config' => $hook->trigger_config ?? [],
            'url' => $hook->url,
            'method' => $hook->method ?? 'POST',
            'headers' => $hook->headers ?? [],
            'timeout_seconds' => $hook->timeout_seconds ?? 15,
            'follow_redirects' => (bool) ($hook->follow_redirects ?? true),
            'auth_type' => $hook->auth_type ?? 'none',
            // The full secret is never sent to the browser. We expose
            // only a "configured" boolean so the UI can prompt for
            // replacement instead of revealing.
            'auth_configured' => ! empty($hook->auth_config),
            'payload_type' => $hook->payload_type ?? 'raw_json',
            'payload_template' => $hook->payload_template,
            'conditions' => $hook->conditions ?? null,
            'queue_enabled' => (bool) ($hook->queue_enabled ?? true),
            'log_body_mode' => $hook->log_body_mode ?? 'partial',
            'retry_strategy' => $hook->retry_strategy ?? null,
        ];
    }

    /**
     * Auth config arrives from the form as JSON in `auth_config_json` so
     * the UI can offer a small JSON editor without piling extra columns
     * onto the form. We decode + validate here and only persist it when
     * the user actually entered something, so saving an existing hook
     * without touching the secret leaves the encrypted value untouched.
     */
    protected function normalizeAuthConfig(array $attributes): array
    {
        if (! array_key_exists('auth_config_json', $attributes)) {
            return $attributes;
        }
        $raw = (string) ($attributes['auth_config_json'] ?? '');
        unset($attributes['auth_config_json']);

        if (trim($raw) === '') {
            // Don't overwrite stored auth_config; UpdateOutboundWebhookAction
            // already drops empty arrays.
            return $attributes;
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $attributes['auth_config'] = $decoded;
        }
        return $attributes;
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
