<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Domain\InboundEndpoint\Actions\CreateInboundEndpointAction;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Actions\DeleteInboundEndpointAction;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Actions\ToggleInboundEndpointAction;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Actions\UpdateInboundEndpointAction;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Goldnead\WebhookManager\Http\Requests\SaveInboundEndpointRequest;
use Goldnead\WebhookManager\Registries\AuthSchemeRegistry;
use Goldnead\WebhookManager\Registries\InboundActionHandlerRegistry;
use Goldnead\WebhookManager\Repositories\InboundEndpointRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Http\Controllers\CP\CpController;

class InboundController extends CpController
{
    public function index(
        Request $request,
        InboundEndpointRepository $repository,
        InboundActionHandlerRegistry $actions,
    ) {
        $this->authorizeAny($request, 'manage inbound endpoints', 'view webhooks');

        $endpoints = $repository->paginate(25, $request->get('q'));
        $rows = $endpoints->getCollection()->map(fn (InboundEndpoint $e) => $this->row($e));

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $rows,
                'meta' => [
                    'current_page' => $endpoints->currentPage(),
                    'last_page' => $endpoints->lastPage(),
                    'per_page' => $endpoints->perPage(),
                    'total' => $endpoints->total(),
                ],
            ]);
        }

        return Inertia::render('webhook-manager::Inbound/Index', [
            'endpoints' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $endpoints->currentPage(),
                    'last_page' => $endpoints->lastPage(),
                    'per_page' => $endpoints->perPage(),
                    'total' => $endpoints->total(),
                ],
            ],
            'createUrl' => cp_route('webhook-manager.inbound.create'),
            'canCreate' => (bool) $request->user()?->can('manage inbound endpoints'),
            'searchTerm' => $request->get('q', ''),
            'actionOptions' => $actions->options(),
            'routePrefix' => trim((string) config('webhook-manager.inbound.route_prefix', '!/webhooks/inbound'), '/'),
        ]);
    }

    public function create(
        Request $request,
        AuthSchemeRegistry $auth,
        InboundActionHandlerRegistry $actions,
    ) {
        $this->authorizeOr403($request, 'manage inbound endpoints');

        $endpoint = new InboundEndpoint([
            'enabled' => true,
            'allowed_methods' => ['POST'],
            'auth_type' => 'static_header',
            'expected_content_type' => 'application/json',
            'max_payload_kb' => 512,
            'replay_protection_enabled' => false,
            'logging_mode' => 'partial',
            'action_type' => 'noop',
        ]);

        return Inertia::render('webhook-manager::Inbound/Edit', [
            'endpoint' => $this->editPayload($endpoint),
            'authOptions' => $auth->options(),
            'actionOptions' => $actions->options(),
            'isNew' => true,
            'saveUrl' => cp_route('webhook-manager.inbound.store'),
            'indexUrl' => cp_route('webhook-manager.inbound.index'),
            'routePrefix' => trim((string) config('webhook-manager.inbound.route_prefix', '!/webhooks/inbound'), '/'),
        ]);
    }

    public function store(SaveInboundEndpointRequest $request, CreateInboundEndpointAction $create)
    {
        $this->authorizeOr403($request, 'manage inbound endpoints');

        $attributes = $this->normalizeAuthConfig($request->validated());
        $attributes = $this->normalizeJsonConfig($attributes, ['mapping_config_json' => 'mapping_config']);
        $attributes = $this->normalizeJsonConfig($attributes, ['action_config_json' => 'action_config']);
        $attributes = $this->normalizeJsonConfig($attributes, ['response_config_json' => 'response_config']);

        $endpoint = ($create)($attributes);

        return redirect(cp_route('webhook-manager.inbound.edit', $endpoint))
            ->with('success', __('webhook-manager::messages.endpoint_created'));
    }

    public function edit(
        Request $request,
        InboundEndpoint $endpoint,
        AuthSchemeRegistry $auth,
        InboundActionHandlerRegistry $actions,
    ) {
        $this->authorizeOr403($request, 'manage inbound endpoints');

        return Inertia::render('webhook-manager::Inbound/Edit', [
            'endpoint' => $this->editPayload($endpoint),
            'authOptions' => $auth->options(),
            'actionOptions' => $actions->options(),
            'isNew' => false,
            'saveUrl' => cp_route('webhook-manager.inbound.update', $endpoint),
            'deleteUrl' => cp_route('webhook-manager.inbound.destroy', $endpoint),
            'toggleUrl' => cp_route('webhook-manager.inbound.toggle', $endpoint),
            'testUrl' => cp_route('webhook-manager.actions.test-inbound', $endpoint),
            'indexUrl' => cp_route('webhook-manager.inbound.index'),
            'routePrefix' => trim((string) config('webhook-manager.inbound.route_prefix', '!/webhooks/inbound'), '/'),
        ]);
    }

    public function update(
        SaveInboundEndpointRequest $request,
        InboundEndpoint $endpoint,
        UpdateInboundEndpointAction $update,
    ) {
        $this->authorizeOr403($request, 'manage inbound endpoints');

        $attributes = $this->normalizeAuthConfig($request->validated());
        $attributes = $this->normalizeJsonConfig($attributes, ['mapping_config_json' => 'mapping_config']);
        $attributes = $this->normalizeJsonConfig($attributes, ['action_config_json' => 'action_config']);
        $attributes = $this->normalizeJsonConfig($attributes, ['response_config_json' => 'response_config']);

        ($update)($endpoint, $attributes);

        return back()->with('success', __('webhook-manager::messages.endpoint_updated'));
    }

    public function destroy(Request $request, InboundEndpoint $endpoint, DeleteInboundEndpointAction $delete)
    {
        $this->authorizeOr403($request, 'manage inbound endpoints');

        ($delete)($endpoint);

        return redirect(cp_route('webhook-manager.inbound.index'))
            ->with('success', __('webhook-manager::messages.endpoint_deleted'));
    }

    public function toggle(Request $request, InboundEndpoint $endpoint, ToggleInboundEndpointAction $toggle)
    {
        $this->authorizeOr403($request, 'manage inbound endpoints');

        $endpoint = ($toggle)($endpoint);

        return back()->with('success', $endpoint->enabled
            ? __('webhook-manager::messages.endpoint_enabled')
            : __('webhook-manager::messages.endpoint_disabled'));
    }

    /** @return array<string,mixed> */
    protected function row(InboundEndpoint $endpoint): array
    {
        return [
            'id' => $endpoint->id,
            'uuid' => $endpoint->uuid,
            'name' => $endpoint->name,
            'handle' => $endpoint->handle,
            'path' => $endpoint->path,
            'auth_type' => $endpoint->auth_type,
            'action_type' => $endpoint->action_type,
            'enabled' => (bool) $endpoint->enabled,
            'edit_url' => cp_route('webhook-manager.inbound.edit', $endpoint),
            'toggle_url' => cp_route('webhook-manager.inbound.toggle', $endpoint),
            'delete_url' => cp_route('webhook-manager.inbound.destroy', $endpoint),
        ];
    }

    /** @return array<string,mixed> */
    protected function editPayload(InboundEndpoint $endpoint): array
    {
        return [
            'id' => $endpoint->id,
            'uuid' => $endpoint->uuid,
            'name' => $endpoint->name,
            'handle' => $endpoint->handle,
            'description' => $endpoint->description,
            'enabled' => (bool) ($endpoint->enabled ?? true),
            'path' => $endpoint->path,
            'allowed_methods' => $endpoint->allowed_methods ?? ['POST'],
            'auth_type' => $endpoint->auth_type ?? 'static_header',
            // Never reveal the secret — UI only knows whether one exists.
            'auth_configured' => ! empty($endpoint->auth_config),
            'expected_content_type' => $endpoint->expected_content_type ?? 'application/json',
            'max_payload_kb' => (int) ($endpoint->max_payload_kb ?? 512),
            'replay_protection_enabled' => (bool) ($endpoint->replay_protection_enabled ?? false),
            'rate_limit_config' => $endpoint->rate_limit_config ?? null,
            'logging_mode' => $endpoint->logging_mode ?? 'partial',
            'mapping_config' => $endpoint->mapping_config ?? null,
            'action_type' => $endpoint->action_type ?? 'noop',
            'action_config' => $endpoint->action_config ?? null,
            'response_config' => $endpoint->response_config ?? null,
        ];
    }

    /**
     * Auth config arrives from the form as JSON in `auth_config_json` so
     * the UI can offer a small JSON editor instead of N specialized inputs.
     * Decode here and only persist when the user actually entered something
     * — empty input means "leave the encrypted value untouched."
     */
    protected function normalizeAuthConfig(array $attributes): array
    {
        if (! array_key_exists('auth_config_json', $attributes)) {
            return $attributes;
        }
        $raw = (string) ($attributes['auth_config_json'] ?? '');
        unset($attributes['auth_config_json']);

        if (trim($raw) === '') {
            return $attributes;
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $attributes['auth_config'] = $decoded;
        }
        return $attributes;
    }

    /**
     * Decode a JSON form input into an array attribute. Used for the
     * advanced JSON editors (mapping/action/response config) where the
     * UI offers raw JSON rather than nested form fields.
     */
    protected function normalizeJsonConfig(array $attributes, array $map): array
    {
        foreach ($map as $jsonKey => $arrayKey) {
            if (! array_key_exists($jsonKey, $attributes)) {
                continue;
            }
            $raw = (string) ($attributes[$jsonKey] ?? '');
            unset($attributes[$jsonKey]);
            if (trim($raw) === '') {
                continue;
            }
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $attributes[$arrayKey] = $decoded;
            }
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
