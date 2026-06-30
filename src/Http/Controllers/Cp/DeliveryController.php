<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Goldnead\WebhookManager\Repositories\DeliveryRepository;
use Goldnead\WebhookManager\Services\DeliveryMaskingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Http\Controllers\CP\CpController;

class DeliveryController extends CpController
{
    /**
     * List deliveries (server-driven Listing).
     *
     * Statamic's <Listing> sends `search` / `sort` / `order` / `page` /
     * `perPage`. Domain filters specific to this listing:
     *   status       — success | failed | pending | retry
     *   trigger      — any registered trigger handle
     *   error_type   — network|timeout|auth|client|server|payload|configuration|internal
     *   webhook_id   — int ID of an outbound webhook
     *   from / to    — ISO-8601 date range bounds
     */
    public function index(Request $request, DeliveryRepository $repository, TriggerRegistry $triggers)
    {
        abort_unless($request->user()?->can('view webhook deliveries'), 403);

        $perPage = (int) $request->get('perPage', 25) ?: 25;
        $search = (string) $request->get('search', $request->get('q', ''));

        $filters = array_filter([
            'status' => $request->get('status'),
            'trigger' => $request->get('trigger'),
            'error_type' => $request->get('error_type'),
            'webhook_id' => $request->get('webhook_id'),
            'from' => $request->get('from'),
            'to' => $request->get('to'),
        ], fn ($v) => $v !== null && $v !== '');

        $deliveries = $repository->paginate($perPage, $search, $filters);
        $triggerLabels = $triggers->options();

        $rows = $deliveries->getCollection()
            ->map(fn (Delivery $d) => $this->row($d, $request, $triggerLabels))
            ->values();

        $listingPayload = [
            'data' => $rows,
            'meta' => [
                'current_page' => $deliveries->currentPage(),
                'last_page' => $deliveries->lastPage(),
                'per_page' => $deliveries->perPage(),
                'total' => $deliveries->total(),
                'from' => $deliveries->firstItem(),
                'to' => $deliveries->lastItem(),
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json($listingPayload);
        }

        return Inertia::render('webhook-manager::Deliveries/Index', [
            'deliveries' => $listingPayload,
            'initialColumns' => $this->indexColumns(),
            'listingUrl' => cp_route('webhook-manager.deliveries.index'),
            'actionUrl' => cp_route('webhook-manager.deliveries.index'),
        ]);
    }

    /**
     * Show a single delivery's full debug view. Bodies are masked when
     * the user lacks the `view sensitive payloads` ability.
     */
    public function show(Request $request, Delivery $delivery, DeliveryMaskingService $masker)
    {
        abort_unless($request->user()?->can('view webhook deliveries'), 403);

        $canViewSensitive = $request->user()?->can('view sensitive payloads') === true;
        $masked = $masker->maskForViewer($delivery, $canViewSensitive);

        $canReplay = $request->user()?->can('replay webhook deliveries') === true
            && $masked->isReplayable();

        return Inertia::render('webhook-manager::Deliveries/Show', [
            'delivery' => $this->showPayload($masked),
            'canReplay' => $canReplay,
            'canViewSensitive' => $canViewSensitive,
            'replayUrl' => $canReplay
                ? cp_route('webhook-manager.actions.replay-delivery', $masked)
                : null,
            'indexUrl' => cp_route('webhook-manager.deliveries.index'),
        ]);
    }

    /**
     * Column definitions for the <Listing> component.
     *
     * Handles match the `cell-{handle}` slot names in Deliveries/Index.vue.
     * Aliases (`url`, `method`, `response_code`, `when`) are kept short
     * for the UI; the row() method maps the actual DB columns to those
     * aliases below.
     *
     * @return array<int,array{handle:string,label:string,visible:bool,sortable:bool}>
     */
    protected function indexColumns(): array
    {
        return [
            ['field' => 'status',        'label' => __('Status'),    'visible' => true,  'sortable' => true],
            ['field' => 'outbound_name', 'label' => __('Trigger'),   'visible' => true,  'sortable' => false],
            ['field' => 'url',           'label' => __('URL'),       'visible' => true,  'sortable' => false],
            ['field' => 'method',        'label' => __('Method'),    'visible' => true,  'sortable' => false],
            ['field' => 'response_code', 'label' => __('Code'),      'visible' => true,  'sortable' => true],
            ['field' => 'attempts',      'label' => __('Attempts'),  'visible' => true,  'sortable' => true],
            ['field' => 'error_type',    'label' => __('Error'),     'visible' => false, 'sortable' => true],
            ['field' => 'when',          'label' => __('When'),      'visible' => true,  'sortable' => true],
        ];
    }

    /**
     * Single-row payload for the listing. Pre-computes badge colours and
     * action URLs, and exposes UI-friendly aliases (`url`, `method`,
     * `response_code`, `when`) over the DB column names so the Vue
     * template stays terse.
     *
     * @param  array<string,string>  $triggerLabels
     * @return array<string,mixed>
     */
    protected function row(Delivery $delivery, Request $request, array $triggerLabels): array
    {
        $canReplay = $delivery->isReplayable()
            && (bool) $request->user()?->can('replay webhook deliveries');

        return [
            'id' => $delivery->id,
            'uuid' => $delivery->uuid,

            'status' => $delivery->status,
            'status_color' => $this->statusColor($delivery->status),

            'trigger_type' => $delivery->trigger_type,
            'trigger_label' => $triggerLabels[$delivery->trigger_type] ?? $delivery->trigger_type,
            // alias used by the `cell-outbound_name` slot in Vue
            'outbound_name' => $triggerLabels[$delivery->trigger_type] ?? $delivery->trigger_type,

            // DB names + UI aliases
            'request_url' => $delivery->request_url,
            'url' => $delivery->request_url,
            'request_method' => $delivery->request_method,
            'method' => $delivery->request_method,
            'method_color' => $this->methodColor($delivery->request_method),

            'response_status' => $delivery->response_status,
            'response_code' => $delivery->response_status,
            'response_code_color' => $this->responseCodeColor($delivery->response_status),

            'attempts' => (int) $delivery->attempts,

            'error_type' => $delivery->error_type,
            'error_type_label' => $this->errorTypeLabel($delivery->error_type),
            'error_type_color' => $this->errorTypeColor($delivery->error_type),

            'created_at' => $delivery->created_at?->toIso8601String(),
            'when' => $delivery->created_at?->toIso8601String(),

            'can_replay' => $canReplay,
            'show_url' => cp_route('webhook-manager.deliveries.show', $delivery),
            'replay_url' => $canReplay
                ? cp_route('webhook-manager.actions.replay-delivery', $delivery)
                : null,
        ];
    }

    /**
     * Full payload for the Show page. Bundles request and response into
     * separate sub-arrays for the side-by-side panels, AND surfaces flat
     * UI-friendly aliases (`url`, `method`, `response_code`, `error`) at
     * the top level — the Vue template uses both shapes, depending on
     * which is more readable for that field.
     *
     * @return array<string,mixed>
     */
    protected function showPayload(Delivery $delivery): array
    {
        return [
            'id' => $delivery->id,
            'uuid' => $delivery->uuid,

            'status' => $delivery->status,
            'status_badge' => $delivery->statusBadge(),
            'status_color' => $this->statusColor($delivery->status),

            'trigger_type' => $delivery->trigger_type,
            'trigger_reference' => $delivery->trigger_reference,
            'correlation_id' => $delivery->correlation_id,

            'attempts' => (int) $delivery->attempts,
            'duration_ms' => $delivery->duration_ms,

            'first_attempted_at' => $delivery->first_attempted_at?->toIso8601String(),
            'last_attempted_at' => $delivery->last_attempted_at?->toIso8601String(),
            'next_retry_at' => $delivery->next_retry_at?->toIso8601String(),
            'first_attempted_human' => $delivery->first_attempted_at?->diffForHumans(),
            'last_attempted_human' => $delivery->last_attempted_at?->diffForHumans(),
            'next_retry_human' => $delivery->next_retry_at?->diffForHumans(),

            // Flat UI-friendly aliases used by the header / status panel.
            'url' => $delivery->request_url,
            'method' => $delivery->request_method,
            'method_color' => $this->methodColor($delivery->request_method),
            'response_code' => $delivery->response_status,
            'response_code_color' => $this->responseCodeColor($delivery->response_status),
            'error' => $delivery->error_message,

            'error_type' => $delivery->error_type,
            'error_type_label' => $this->errorTypeLabel($delivery->error_type),
            'error_type_color' => $this->errorTypeColor($delivery->error_type),
            'error_message' => $delivery->error_message,

            'request' => [
                'url' => $delivery->request_url,
                'method' => $delivery->request_method,
                'method_color' => $this->methodColor($delivery->request_method),
                'headers' => $delivery->request_headers ?? [],
                'body' => $delivery->request_body,
            ],

            'response' => [
                'status' => $delivery->response_status,
                'status_color' => $this->responseCodeColor($delivery->response_status),
                'headers' => $delivery->response_headers ?? [],
                'body' => $delivery->response_body,
            ],

            'curl' => $this->buildCurl($delivery),

            'is_replayable' => $delivery->isReplayable(),
            'can_replay' => $delivery->isReplayable(),

            'created_at' => $delivery->created_at?->toIso8601String(),
        ];
    }

    /**
     * Build a copy-paste cURL command from the delivery snapshot.
     */
    protected function buildCurl(Delivery $delivery): string
    {
        $parts = ['curl -X '.$delivery->request_method];

        foreach ((array) $delivery->request_headers as $name => $value) {
            $value = is_array($value) ? implode(',', $value) : (string) $value;
            $parts[] = "  -H '".addslashes($name).': '.addslashes($value)."'";
        }

        if ($body = $delivery->request_body) {
            $parts[] = "  -d '".addslashes($body)."'";
        }

        $parts[] = "  '".addslashes($delivery->request_url)."'";

        return implode(" \\\n", $parts);
    }

    // ── Colour helpers ──────────────────────────────────────────────────────

    protected function statusColor(?string $status): string
    {
        return match ($status) {
            'success' => 'green',
            'failed' => 'red',
            'pending' => 'amber',
            'retry' => 'amber',
            'processing' => 'blue',
            default => 'gray',
        };
    }

    protected function methodColor(?string $method): string
    {
        return match (strtoupper((string) $method)) {
            'GET' => 'blue',
            'POST' => 'green',
            'PUT' => 'amber',
            'PATCH' => 'amber',
            'DELETE' => 'red',
            default => 'gray',
        };
    }

    protected function responseCodeColor(int|string|null $code): string
    {
        $code = (int) $code;
        if ($code >= 500) return 'red';
        if ($code >= 400) return 'amber';
        if ($code >= 300) return 'blue';
        if ($code >= 200) return 'green';
        return 'gray';
    }

    protected function errorTypeColor(?string $type): string
    {
        return match ($type) {
            'network' => 'orange',
            'timeout' => 'amber',
            'auth' => 'red',
            'client' => 'yellow',
            'server' => 'red',
            'payload' => 'purple',
            'configuration' => 'blue',
            'internal' => 'gray',
            default => 'gray',
        };
    }

    protected function errorTypeLabel(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }
        $key = 'webhook-manager::messages.failure_types.'.$type;
        $translated = __($key);
        return $translated === $key ? $type : $translated;
    }
}
