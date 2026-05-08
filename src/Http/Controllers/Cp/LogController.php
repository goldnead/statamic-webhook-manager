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
     *   error_type     — network|timeout|auth|client|server|payload|configuration|internal
     *                    (UI vocabulary; mapped to DB column `type` before the repository call)
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
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json($listingPayload);
        }

        return Inertia::render('webhook-manager::Logs/Index', [
            'logs' => $listingPayload,
            'initialColumns' => $this->indexColumns(),
            'listingUrl' => cp_route('webhook-manager.logs.index'),
            'actionUrl' => cp_route('webhook-manager.logs.index'),
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
            ['handle' => 'level',          'label' => __('Level'),          'visible' => true, 'sortable' => true],
            ['handle' => 'message',        'label' => __('Message'),        'visible' => true, 'sortable' => false],
            ['handle' => 'correlation_id', 'label' => __('Correlation ID'), 'visible' => true, 'sortable' => false],
            ['handle' => 'error_type',     'label' => __('Error Type'),     'visible' => true, 'sortable' => true],
            ['handle' => 'created_at',     'label' => __('When'),           'visible' => true, 'sortable' => true],
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
            'error_type_label' => $this->errorTypeLabel($errorType),
            'error_type_color' => $this->errorTypeColor($errorType),

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
     * Map error_type / type value → Statamic Badge colour token.
     *
     * Eight classes per PRD §12.5 / FailureClassifier.
     */
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

    /**
     * Reuse the `failure_types.*` strings already in messages.php so the
     * label rendered in the badge matches what the rest of the app uses.
     */
    protected function errorTypeLabel(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }
        $key = 'webhook-manager::messages.failure_types.'.$type;
        $translated = __($key);
        // If there is no translation registered, __() returns the key
        // unchanged. Fall back to the raw type in that case.
        return $translated === $key ? $type : $translated;
    }
}
