<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Domain\Log\Models\LogEntry;
use Goldnead\WebhookManager\Repositories\LogRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Http\Controllers\CP\CpController;

class LogController extends CpController
{
    /**
     * List log entries (server-driven Listing).
     *
     * Statamic's <Listing> component issues AJAX GETs with these params:
     *   search / q     — full-text search (legacy `q` kept for bookmarks)
     *   sort / order   — column handle + asc|desc (not yet used downstream)
     *   page / perPage — pagination
     *
     * Domain filters specific to this listing:
     *   level          — debug|info|warning|error
     *   error_type     — a log EVENT type (`inbound_received`, `delivery_failed`,
     *                    `rule_executed`, …), not one of a delivery's eight
     *                    failure classes; this docblock claimed the latter for
     *                    months. Mapped to DB column `type` before the
     *                    repository call, so the repository stays unaware of the
     *                    UI-side renaming.
     *   correlation_id — partial-match text
     *   from / to      — ISO-8601 date range bounds
     */
    public function index(Request $request, LogRepository $repository)
    {
        abort_unless($request->user()?->can('view webhooks'), 403);

        $perPage = (int) $request->get('perPage', 25) ?: 25;
        $search = (string) $request->get('search', $request->get('q', ''));

        // Map UI vocabulary `error_type` → DB column `type` so the
        // repository stays unaware of any UI-side renaming.
        $filters = array_filter([
            'level' => $request->get('level'),
            'type' => $request->get('error_type'),
            'correlation_id' => $request->get('correlation_id'),
            'from' => $request->get('from'),
            'to' => $request->get('to'),
        ], fn ($v) => $v !== null && $v !== '');

        $logs = $repository->paginate($perPage, $search, $filters);

        $rows = $logs->getCollection()
            ->map(fn (LogEntry $log) => $this->row($log))
            ->values();

        $listingPayload = [
            'data' => $rows,
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
                // Pflicht fuer <Listing>: fehlt der Schluessel, laufen die
                // Spalten auf undefined und der Fehler landet im generischen
                // `.catch` als „Etwas ist schiefgelaufen" — bei HTTP 200.
                'columns' => $this->indexColumns(),
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json($listingPayload);
        }

        return Inertia::render('webhook-manager::Logs/Index', [
            'logs' => $listingPayload,
            'initialColumns' => $this->indexColumns(),
            'listingUrl' => cp_route('webhook-manager.logs.index'),
            // No bulk-action endpoint exists for this listing, so `actionUrl`
            // is null on purpose. It used to point back at this very (GET)
            // index route, which is what core reads as "there are actions": the
            // listing then drew a checkbox column whose bulk menu could never
            // fill — `POST {url}/list` answers 404. Checkboxes without an
            // action behind them are worse than none (T01 brief, 05.09.2026).
            //
            // `:allow-bulk-actions="false"` does NOT fix this: core derives the
            // checkbox column from `actionUrl` alone
            // (`Listing.vue:183` — `allowsSelections = (selections || hasActions)`),
            // and that flag only hides the bulk toolbar. Dropping the URL is
            // what removes the column. The per-row "…" menu stays: it is built
            // from this page's own `#prepended-row-actions` slot, not from core
            // actions.
            'actionUrl' => null,
        ]);
    }

    /**
     * Column definitions for the server-driven Listing component.
     *
     * @return array<int,array{handle:string,label:string,visible:bool,sortable:bool}>
     */
    protected function indexColumns(): array
    {
        return [
            ['field' => 'level',          'label' => __('webhook-manager::messages.cp.col_level'),          'visible' => true, 'sortable' => true],
            ['field' => 'message',        'label' => __('webhook-manager::messages.cp.col_message'),        'visible' => true, 'sortable' => false],
            ['field' => 'correlation_id', 'label' => __('webhook-manager::messages.cp.col_correlation_id'), 'visible' => true, 'sortable' => false],
            // „Fehlerart" was the wrong heading: this column holds the log
            // event's type, and most of those events are not failures.
            ['field' => 'error_type',     'label' => __('webhook-manager::messages.cp.col_log_type'),       'visible' => true, 'sortable' => true],
            ['field' => 'created_at',     'label' => __('webhook-manager::messages.cp.col_when'),           'visible' => true, 'sortable' => true],
        ];
    }

    /**
     * Shape a single LogEntry into the row array consumed by the Vue
     * Listing component. Pre-computes display helpers so the Vue layer
     * stays logic-free, and renames the DB `type` column to the
     * UI-friendly `error_type` for consistency with PRD §12.5.
     *
     * @return array<string,mixed>
     */
    protected function row(LogEntry $log): array
    {
        $errorType = $log->type;

        return [
            'id' => $log->id,
            'uuid' => $log->uuid,

            // Raw values
            'level' => $log->level,
            'message' => $log->message,
            'correlation_id' => $log->correlation_id,
            'error_type' => $errorType,
            'created_at' => $log->created_at?->toIso8601String(),

            // Pre-computed display helpers
            'level_color' => $this->levelColor($log->level),
            'level_label' => $this->levelLabel($log->level),
            'error_type_label' => $this->logTypeLabel($errorType),
            'error_type_color' => $this->logTypeColor($errorType),

            'context' => $log->context,
        ];
    }

    /** Map log level → Statamic Badge colour token. */
    protected function levelColor(?string $level): string
    {
        return match ($level) {
            'error' => 'red',
            'warning' => 'amber',
            'info' => 'blue',
            'debug' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Colour for a log event type.
     *
     * This used to be a private copy of the delivery listing's error-type
     * colours (`network`, `timeout`, `auth`, …) with a docblock claiming it
     * reused the `failure_types.*` strings. It did neither: a log entry's
     * `type` column holds an EVENT handle — `inbound_received`,
     * `delivery_failed`, `rule_executed` — from a different vocabulary
     * altogether. Not one of the eight failure classes ever appears in it, so
     * every lookup missed, every badge came out grey, and every label fell
     * back to the raw handle. Adopting PresentsDeliveryErrors here would have
     * removed the third copy and kept the wrong vocabulary; the copy is gone
     * and the vocabulary is now the one this column actually carries.
     *
     * Grouped by what happened rather than per handle: a refusal is red, a
     * completed run green, an accepted request blue, the rest amber.
     */
    protected function logTypeColor(?string $type): string
    {
        if ($type === null) {
            return 'gray';
        }

        return match (true) {
            in_array($type, ['delivery_success', 'inbound_action_succeeded', 'replay_executed', 'rule_executed'], true) => 'green',
            in_array($type, ['inbound_received', 'inbound_audit'], true) => 'blue',
            str_ends_with($type, '_failed'), str_ends_with($type, '_exception') => 'red',
            in_array($type, ['inbound_auth_failed', 'inbound_endpoint_not_found', 'inbound_method_not_allowed', 'inbound_replay_blocked'], true) => 'red',
            default => 'amber',
        };
    }

    /**
     * Wording for a log event type.
     *
     * Falls back to the raw handle when a type has no entry — never to the
     * translation key (Laravel hands the key back on a miss, and a key printed
     * at a reader is the defect this whole pass exists to remove) and never to
     * a blank, which would hide which event it was.
     */
    protected function logTypeLabel(?string $type): ?string
    {
        return $this->translateHandle('cp.log_types.', $type);
    }

    /** Wording for a log level (`info`, `warning`, `error`, `debug`). */
    protected function levelLabel(?string $level): ?string
    {
        return $this->translateHandle('cp.log_levels.', $level);
    }

    private function translateHandle(string $prefix, ?string $handle): ?string
    {
        if ($handle === null || $handle === '') {
            return null;
        }

        $key = 'webhook-manager::messages.'.$prefix.$handle;
        $translated = __($key);

        return is_string($translated) && $translated !== $key ? $translated : $handle;
    }
}
