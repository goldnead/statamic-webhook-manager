<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface;
use Goldnead\WebhookManager\Contracts\Repositories\TemplateRepositoryInterface;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Actions\CreateOutboundWebhookAction;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Actions\DeleteOutboundWebhookAction;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Actions\ToggleOutboundWebhookAction;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Actions\UpdateOutboundWebhookAction;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Domain\Template\Models\Template;
use Goldnead\WebhookManager\Http\Controllers\Cp\Concerns\PresentsOutboundWebhooks;
use Goldnead\WebhookManager\Http\Requests\SaveOutboundWebhookRequest;
use Goldnead\WebhookManager\Registries\AuthSchemeRegistry;
use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Http\Controllers\CP\CpController;

class OutboundController extends CpController
{
    use PresentsOutboundWebhooks;

    public function index(
        Request $request,
        OutboundWebhookRepositoryInterface $repository,
        TriggerRegistry $triggers,
    ) {
        $this->authorizeAny($request, 'manage outbound webhooks', 'view webhooks');

        // Statamic's <Listing> sends `search`, `sort`, `order`, `page`,
        // `perPage`. We accept the legacy `q` param too to keep older
        // bookmarked links working.
        $perPage = (int) $request->get('perPage', 25) ?: 25;
        $search = $request->get('search', $request->get('q', ''));

        $hooks = $repository->paginate($perPage, $search);
        $triggerLabels = $triggers->options();

        $rows = $hooks->getCollection()
            ->map(fn (OutboundWebhook $hook) => $this->outboundRow($hook, $request, $triggerLabels))
            ->values();

        $listingPayload = [
            'data' => $rows,
            'meta' => [
                'current_page' => $hooks->currentPage(),
                'last_page' => $hooks->lastPage(),
                'per_page' => $hooks->perPage(),
                'total' => $hooks->total(),
                'from' => $hooks->firstItem(),
                'to' => $hooks->lastItem(),
                // Pflicht fuer <Listing>: fehlt der Schluessel, laufen die
                // Spalten auf undefined und der Fehler landet im generischen
                // `.catch` als „Etwas ist schiefgelaufen" — bei HTTP 200.
                'columns' => $this->outboundColumns(),
            ],
        ];

        // <Listing> issues an AJAX GET against the page URL whenever
        // search/sort/page changes. Returning JSON here lets it refresh
        // without a full Inertia round-trip.
        if ($request->wantsJson()) {
            return response()->json($listingPayload);
        }

        return Inertia::render('webhook-manager::Outbound/Index', [
            'webhooks' => $listingPayload,
            'initialColumns' => $this->outboundColumns(),
            'listingUrl' => cp_route('webhook-manager.outbound.index'),
            // The real action endpoint. This used to point back at the index
            // route, which gave the listing a checkbox column and an empty
            // bulk menu — checkboxes that did nothing. <Listing> posts the
            // selection to this URL and asks `{url}/list` which actions apply.
            'actionUrl' => cp_route('webhook-manager.outbound.actions.run'),
            'createUrl' => cp_route('webhook-manager.outbound.create'),
            'integrationsUrl' => cp_route('webhook-manager.integrations.index'),
            'canCreate' => (bool) $request->user()?->can('manage outbound webhooks'),
            'searchTerm' => $search,
            'triggerOptions' => $triggerLabels,
        ]);
    }

    public function create(
        Request $request,
        TriggerRegistry $triggers,
        AuthSchemeRegistry $auth,
        TemplateRepositoryInterface $templates,
    ) {
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
            'methodOptions' => ['POST', 'GET', 'PUT', 'PATCH', 'DELETE'],
            'payloadTypeOptions' => $this->payloadTypeOptions(),
            'logBodyModeOptions' => $this->logBodyModeOptions(),
            'availableTemplates' => $this->availableTemplates($templates),
            'isNew' => true,
            'canDelete' => false,
            'canTest' => false,
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
        OutboundWebhook $webhookOutbound,
        TriggerRegistry $triggers,
        AuthSchemeRegistry $auth,
        TemplateRepositoryInterface $templates,
    ) {
        $this->authorizeOr403($request, 'manage outbound webhooks');

        $user = $request->user();

        return Inertia::render('webhook-manager::Outbound/Edit', [
            'webhook' => $this->editPayload($webhookOutbound),
            'triggerOptions' => $triggers->options(),
            'authOptions' => $auth->options(),
            'methodOptions' => ['POST', 'GET', 'PUT', 'PATCH', 'DELETE'],
            'payloadTypeOptions' => $this->payloadTypeOptions(),
            'logBodyModeOptions' => $this->logBodyModeOptions(),
            'availableTemplates' => $this->availableTemplates($templates),
            'isNew' => false,
            'canDelete' => (bool) $user?->can('manage outbound webhooks'),
            // `??` was the bug: `can()` returns a boolean, never null, so the
            // fallback never fired and a `false` from the (unregistered)
            // dedicated ability hid the Test button from everyone.
            'canTest' => $this->canTest($user),
            'saveUrl' => cp_route('webhook-manager.outbound.update', $webhookOutbound),
            'deleteUrl' => cp_route('webhook-manager.outbound.destroy', $webhookOutbound),
            'toggleUrl' => cp_route('webhook-manager.outbound.toggle', $webhookOutbound),
            'testUrl' => cp_route('webhook-manager.actions.test-outbound', $webhookOutbound),
            'indexUrl' => cp_route('webhook-manager.outbound.index'),
        ]);
    }

    public function update(
        SaveOutboundWebhookRequest $request,
        OutboundWebhook $webhookOutbound,
        UpdateOutboundWebhookAction $update,
    ) {
        $this->authorizeOr403($request, 'manage outbound webhooks');

        $attributes = $this->normalizeAuthConfig($request->validated());
        ($update)($webhookOutbound, $attributes);

        return back()->with('success', __('webhook-manager::messages.updated'));
    }

    public function destroy(Request $request, OutboundWebhook $webhookOutbound, DeleteOutboundWebhookAction $delete)
    {
        $this->authorizeOr403($request, 'manage outbound webhooks');

        ($delete)($webhookOutbound);

        return redirect(cp_route('webhook-manager.outbound.index'))
            ->with('success', __('webhook-manager::messages.deleted'));
    }

    public function toggle(Request $request, OutboundWebhook $webhookOutbound, ToggleOutboundWebhookAction $toggle)
    {
        $this->authorizeOr403($request, 'manage outbound webhooks');

        $webhookOutbound = ($toggle)($webhookOutbound);

        return back()->with('success', $webhookOutbound->enabled
            ? __('webhook-manager::messages.enabled')
            : __('webhook-manager::messages.disabled'));
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
            'payload_template_handle' => $hook->payload_template_handle,
            'conditions' => $hook->conditions ?? null,
            'queue_enabled' => (bool) ($hook->queue_enabled ?? true),
            'log_body_mode' => $hook->log_body_mode ?? 'partial',
            'retry_strategy' => $hook->retry_strategy ?? null,
        ];
    }

    /** @return array<string,string> */
    protected function payloadTypeOptions(): array
    {
        return [
            'raw_json' => __('webhook-manager::messages.cp.payload_raw_json'),
            'mapped' => __('webhook-manager::messages.cp.payload_mapped'),
            'form' => __('webhook-manager::messages.cp.payload_form'),
        ];
    }

    /** @return array<string,string> */
    protected function logBodyModeOptions(): array
    {
        return [
            'full' => __('webhook-manager::messages.cp.log_full'),
            'partial' => __('webhook-manager::messages.cp.log_partial'),
            'none' => __('webhook-manager::messages.cp.log_none'),
        ];
    }

    /**
     * Map of `handle => "Name (handle)"` for the library template picker
     * in the Outbound edit screen. Filtered to outbound-body templates so
     * a notification template can't accidentally be wired to an HTTP body.
     *
     * @return array<string,string>
     */
    protected function availableTemplates(TemplateRepositoryInterface $templates): array
    {
        $opts = [];
        foreach ($templates->ofType(Template::TYPE_OUTBOUND_BODY) as $template) {
            $opts[$template->handle] = $template->name.' ('.$template->handle.')';
        }

        return $opts;
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
