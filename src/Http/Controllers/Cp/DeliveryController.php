<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Http\Controllers\Cp\Concerns\PresentsDeliveryErrors;
use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Goldnead\WebhookManager\Repositories\DeliveryRepository;
use Goldnead\WebhookManager\Services\DeliveryMaskingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Http\Controllers\CP\CpController;

class DeliveryController extends CpController
{
    use PresentsDeliveryErrors;

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
     *   subject_type / subject_id — the object the delivery was about
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
            'subject_type' => $request->get('subject_type'),
            'subject_id' => $request->get('subject_id'),
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
                // Pflicht fuer <Listing>: fehlt der Schluessel, laufen die
                // Spalten auf undefined und der Fehler landet im generischen
                // `.catch` als „Etwas ist schiefgelaufen" — bei HTTP 200.
                'columns' => $this->indexColumns(),
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json($listingPayload);
        }

        return Inertia::render('webhook-manager::Deliveries/Index', [
            'deliveries' => $listingPayload,
            'initialColumns' => $this->indexColumns(),
            'listingUrl' => cp_route('webhook-manager.deliveries.index'),
            // The real action endpoint (bulk replay). Pointing this at the
            // index route, as it did, gave the listing checkboxes with an
            // empty bulk menu.
            'actionUrl' => cp_route('webhook-manager.deliveries.actions.run'),
            'subjectTypes' => $this->subjectTypeOptions($repository),
            'subjectFilter' => [
                'type' => $filters['subject_type'] ?? null,
                'id' => isset($filters['subject_id']) ? (string) $filters['subject_id'] : null,
            ],
        ]);
    }

    /**
     * The deliveries recorded about one object, as JSON.
     *
     * Read by the `webhook-deliveries-for-subject` component that another
     * addon embeds on its own page (a payment, an offer), so it answers with
     * the same row shape as the listing and applies the same permission.
     * Brand scope comes from the Delivery model's global scope.
     */
    public function forSubject(Request $request, DeliveryRepository $repository, TriggerRegistry $triggers)
    {
        abort_unless($request->user()?->can('view webhook deliveries'), 403);

        // A JSON body on a GET is read before the query string, so an
        // integrator sending `{"subject_id": 77}` would arrive with an int
        // and fail the string rule for a valid id. The column is a string;
        // compare it as one.
        $request->merge(collect(['subject_type', 'subject_id'])
            ->filter(fn (string $key) => is_scalar($request->input($key)))
            ->mapWithKeys(fn (string $key) => [$key => (string) $request->input($key)])
            ->all());

        $validated = $request->validate([
            'subject_type' => ['required', 'string', 'max:64'],
            'subject_id' => ['required', 'string', 'max:64'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = (int) ($validated['limit'] ?? 20);
        $triggerLabels = $triggers->options();

        $rows = $repository
            ->forSubject($validated['subject_type'], $validated['subject_id'], $limit)
            ->map(fn (Delivery $d) => $this->row($d, $request, $triggerLabels))
            ->values();

        return response()->json([
            'data' => $rows,
            'total' => $repository->countForSubject($validated['subject_type'], $validated['subject_id']),
        ]);
    }

    /**
     * Options for the subject-type filter: the configured types plus any
     * type that is actually present in the log, so a type another addon
     * wrote without registering it is still filterable.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function subjectTypeOptions(DeliveryRepository $repository): array
    {
        $configured = array_map('strval', array_keys((array) config('webhook-manager.subjects', [])));
        $types = array_values(array_unique([...$configured, ...$repository->subjectTypesInUse()]));

        return array_map(fn (string $type) => [
            'value' => $type,
            'label' => $this->subjectTypeLabel($type),
        ], $types);
    }

    protected function subjectTypeLabel(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        $key = 'webhook-manager::messages.subject_types.'.$type;
        $translated = __($key);

        return is_string($translated) && $translated !== $key ? $translated : ucfirst($type);
    }

    /**
     * Show a single delivery's full debug view. Bodies are masked when
     * the user lacks the `view sensitive payloads` ability.
     */
    public function show(
        Request $request,
        Delivery $delivery,
        DeliveryMaskingService $masker,
        TriggerRegistry $triggers,
    ) {
        abort_unless($request->user()?->can('view webhook deliveries'), 403);

        $canViewSensitive = $request->user()?->can('view sensitive payloads') === true;
        $masked = $masker->maskForViewer($delivery, $canViewSensitive);

        $canReplay = $request->user()?->can('replay webhook deliveries') === true
            && $masked->isReplayable();

        return Inertia::render('webhook-manager::Deliveries/Show', [
            'delivery' => $this->showPayload($masked, $triggers->options()),
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
     * @return array<int,array{field:string,label:mixed,visible:bool,sortable:bool}>
     */
    protected function indexColumns(): array
    {
        return [
            ['field' => 'status',        'label' => __('Status'),    'visible' => true,  'sortable' => true],
            ['field' => 'outbound_name', 'label' => __('Trigger'),   'visible' => true,  'sortable' => false],
            ['field' => 'subject',       'label' => __('webhook-manager::messages.subject'), 'visible' => true, 'sortable' => false],
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

            // The object the delivery was about (null when unresolved).
            'subject_type' => $delivery->subject_type,
            'subject_id' => $delivery->subject_id,
            'subject_label' => $this->subjectTypeLabel($delivery->subject_type),
            'subject' => $delivery->subject_type !== null && $delivery->subject_id !== null
                ? $delivery->subject_type.' #'.$delivery->subject_id
                : null,

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
    protected function showPayload(Delivery $delivery, array $triggerLabels = []): array
    {
        return [
            'id' => $delivery->id,
            'uuid' => $delivery->uuid,

            'status' => $delivery->status,
            'status_badge' => $delivery->statusBadge(),
            'status_color' => $this->statusColor($delivery->status),

            'trigger_type' => $delivery->trigger_type,
            'trigger_label' => $triggerLabels[$delivery->trigger_type] ?? $delivery->trigger_type,
            'trigger_reference' => $delivery->trigger_reference,
            'subject_type' => $delivery->subject_type,
            'subject_id' => $delivery->subject_id,
            'subject_label' => $this->subjectTypeLabel($delivery->subject_type),
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
        if ($code >= 500) {
            return 'red';
        }
        if ($code >= 400) {
            return 'amber';
        }
        if ($code >= 300) {
            return 'blue';
        }
        if ($code >= 200) {
            return 'green';
        }

        return 'gray';
    }
}
