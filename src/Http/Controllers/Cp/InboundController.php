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
use Goldnead\WebhookManager\Contracts\Repositories\InboundEndpointRepositoryInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Http\Controllers\CP\CpController;

class InboundController extends CpController
{
    public function index(
        Request $request,
        InboundEndpointRepositoryInterface $repository,
        InboundActionHandlerRegistry $actions,
    ) {
        $this->authorizeAny($request, 'manage inbound endpoints', 'view webhooks');

        // Statamic's <Listing> sends `search`, `sort`, `order`, `page`,
        // `perPage`. We accept the legacy `q` param too to keep older
        // bookmarked links working.
        $perPage = (int) $request->get('perPage', 25) ?: 25;
        $search  = $request->get('search', $request->get('q', ''));

        $endpoints = $repository->paginate($perPage, $search);

        $rows = $endpoints->getCollection()
            ->map(fn (InboundEndpoint $e) => $this->row($e, $request))
            ->values();

        $listingPayload = [
            'data' => $rows,
            'meta' => [
                'current_page' => $endpoints->currentPage(),
                'last_page'    => $endpoints->lastPage(),
                'per_page'     => $endpoints->perPage(),
                'total'        => $endpoints->total(),
                'from'         => $endpoints->firstItem(),
                'to'           => $endpoints->lastItem(),
            ],
        ];

        // <Listing> issues an AJAX GET against the page URL whenever
        // search/sort/page changes. Returning JSON here lets it refresh
        // without a full Inertia round-trip.
        if ($request->wantsJson()) {
            return response()->json($listingPayload);
        }

        return Inertia::render('webhook-manager::Inbound/Index', [
            'endpoints'      => $listingPayload,
            'initialColumns' => $this->indexColumns(),
            'listingUrl'     => cp_route('webhook-manager.inbound.index'),
            'actionUrl'      => cp_route('webhook-manager.inbound.index'),
            'createUrl'      => cp_route('webhook-manager.inbound.create'),
            'canCreate'      => (bool) $request->user()?->can('manage inbound endpoints'),
            'searchTerm'     => $search,
            'actionOptions'  => $actions->options(),
            'routePrefix'    => trim((string) config('webhook-manager.inbound.route_prefix', 'webhooks/inbound'), '/'),
        ]);
    }

    public function create(
        Request $request,
        AuthSchemeRegistry $auth,
        InboundActionHandlerRegistry $actions,
    ) {
        $this->authorizeOr403($request, 'manage inbound endpoints');

        $endpoint = new InboundEndpoint([
            'enabled'                   => true,
            'allowed_methods'           => ['POST'],
            'auth_type'                 => 'static_header',
            'expected_content_type'     => 'application/json',
            'max_payload_kb'            => 512,
            'replay_protection_enabled' => false,
            'logging_mode'              => 'partial',
            'action_type'               => 'noop',
        ]);

        return Inertia::render('webhook-manager::Inbound/Edit', [
            'endpoint'    => $this->editPayload($endpoint),
            'authOptions' => $auth->options(),
            'actionOptions' => $actions->options(),
            'isNew'       => true,
            'canDelete'   => false,
            'saveUrl'     => cp_route('webhook-manager.inbound.store'),
            'indexUrl'    => cp_route('webhook-manager.inbound.index'),
            'routePrefix' => trim((string) config('webhook-manager.inbound.route_prefix', 'webhooks/inbound'), '/'),
        ]);
    }

    public function store(
        SaveInboundEndpointRequest $request,
        CreateInboundEndpointAction $create,
    ) {
        $this->authorizeOr403($request, 'manage inbound endpoints');

        $attributes = $this->normalizeAuthConfig($request->validated());
        $attributes = $this->normalizeJsonConfig($attributes, ['mapping_config_json'  => 'mapping_config']);
        $attributes = $this->normalizeJsonConfig($attributes, ['action_config_json'   => 'action_config']);
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

        $user = $request->user();

        return Inertia::render('webhook-manager::Inbound/Edit', [
            'endpoint'      => $this->editPayload($endpoint),
            'authOptions'   => $auth->options(),
            'actionOptions' => $actions->options(),
            'isNew'         => false,
            'canDelete'     => (bool) $user?->can('manage inbound endpoints'),
            'saveUrl'       => cp_route('webhook-manager.inbound.update', $endpoint),
            'deleteUrl'     => cp_route('webhook-manager.inbound.destroy', $endpoint),
            'toggleUrl'     => cp_route('webhook-manager.inbound.toggle', $endpoint),
            'testUrl'       => cp_route('webhook-manager.actions.test-inbound', $endpoint),
            'indexUrl'      => cp_route('webhook-manager.inbound.index'),
            'routePrefix'   => trim((string) config('webhook-manager.inbound.route_prefix', 'webhooks/inbound'), '/'),
        ]);
    }

    public function update(
        SaveInboundEndpointRequest $request,
        InboundEndpoint $endpoint,
        UpdateInboundEndpointAction $update,
    ) {
        $this->authorizeOr403($request, 'manage inbound endpoints');

        $attributes = $this->normalizeAuthConfig($request->validated());
        $attributes = $this->normalizeJsonConfig($attributes, ['mapping_config_json'  => 'mapping_config']);
        $attributes = $this->normalizeJsonConfig($attributes, ['action_config_json'   => 'action_config']);
        $attributes = $this->normalizeJsonConfig($attributes, ['response_config_json' => 'response_config']);

        ($update)($endpoint, $attributes);

        return back()->with('success', __('webhook-manager::messages.endpoint_updated'));
    }

    public function destroy(
        Request $request,
        InboundEndpoint $endpoint,
        DeleteInboundEndpointAction $delete,
    ) {
        $this->authorizeOr403($request, 'manage inbound endpoints');

        ($delete)($endpoint);

        return redirect(cp_route('webhook-manager.inbound.index'))
            ->with('success', __('webhook-manager::messages.endpoint_deleted'));
    }

    public function toggle(
        Request $request,
        InboundEndpoint $endpoint,
        ToggleInboundEndpointAction $toggle,
    ) {
        $this->authorizeOr403($request, 'manage inbound endpoints');

        $endpoint = ($toggle)($endpoint);

        return back()->with('success', $endpoint->enabled
            ? __('webhook-manager::messages.endpoint_enabled')
            : __('webhook-manager::messages.endpoint_disabled'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Column definitions for the <Listing> component on the index page.
     *
     * Each entry maps to a slot name (`cell-{field}`) the Vue page binds.
     *
     * @return array<int,array{field:string,label:string,sortable?:bool,visible?:bool}>
     */
    protected function indexColumns(): array
    {
        return [
            ['field' => 'name',        'label' => __('Name'),    'sortable' => true,  'visible' => true],
            ['field' => 'path',        'label' => __('Path'),    'sortable' => false, 'visible' => true],
            ['field' => 'auth_type',   'label' => __('Auth'),    'sortable' => true,  'visible' => true],
            ['field' => 'action_type', 'label' => __('Action'),  'sortable' => true,  'visible' => true],
            ['field' => 'enabled',     'label' => __('Status'),  'sortable' => true,  'visible' => true],
        ];
    }

    /**
     * Single-row payload for the listing. Includes pre-computed
     * permission flags + helper URLs so the Vue page never has to
     * check abilities or build routes itself.
     *
     * @return array<string,mixed>
     */
    protected function row(InboundEndpoint $endpoint, Request $request): array
    {
        $user      = $request->user();
        $canManage = (bool) $user?->can('manage inbound endpoints');

        return [
            'id'          => $endpoint->id,
            'uuid'        => $endpoint->uuid,
            'name'        => $endpoint->name,
            'handle'      => $endpoint->handle,
            'path'        => $endpoint->path,
            'auth_type'   => $endpoint->auth_type,
            'action_type' => $endpoint->action_type,
            'enabled'     => (bool) $endpoint->enabled,

            // Permissions surfaced to the UI so v-if conditions stay
            // declarative and don't leak ability strings into Vue.
            'can_edit'    => $canManage,
            'can_toggle'  => $canManage,
            'can_delete'  => $canManage,

            'edit_url'    => cp_route('webhook-manager.inbound.edit', $endpoint),
            'toggle_url'  => cp_route('webhook-manager.inbound.toggle', $endpoint),
            'delete_url'  => cp_route('webhook-manager.inbound.destroy', $endpoint),
        ];
    }

    /**
     * Full payload for the edit/create view.
     *
     * The full auth_config secret is never sent to the browser.
     * We expose only an `auth_configured` flag so the UI can prompt
     * for replacement instead of revealing the encrypted value.
     *
     * @return array<string,mixed>
     */
    protected function editPayload(InboundEndpoint $endpoint): array
    {
        return [
            'id'                        => $endpoint->id,
            'uuid'                      => $endpoint->uuid,
            'name'                      => $endpoint->name,
            'handle'                    => $endpoint->handle,
            'description'               => $endpoint->description,
            'enabled'                   => (bool) ($endpoint->enabled ?? true),
            'path'                      => $endpoint->path,
            'allowed_methods'           => $endpoint->allowed_methods ?? ['POST'],
            'auth_type'                 => $endpoint->auth_type ?? 'static_header',
            'auth_configured'           => ! empty($endpoint->auth_config),
            'expected_content_type'     => $endpoint->expected_content_type ?? 'application/json',
            'max_payload_kb'            => (int) ($endpoint->max_payload_kb ?? 512),
            'replay_protection_enabled' => (bool) ($endpoint->replay_protection_enabled ?? false),
            'logging_mode'              => $endpoint->logging_mode ?? 'partial',
            'mapping_config'            => $endpoint->mapping_config ?? null,
            'action_type'               => $endpoint->action_type ?? 'noop',
            'action_config'             => $endpoint->action_config ?? null,
            'response_config'           => $endpoint->response_config ?? null,
        ];
    }

    /**
     * Auth config arrives from the form as JSON in `auth_config_json` so
     * the UI can offer a small JSON editor without piling extra columns
     * onto the form. We decode + validate here and only persist it when
     * the user actually entered something, so saving an existing endpoint
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
     *
     * @param  array<string,string>  $map  ['json_key' => 'array_key']
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
