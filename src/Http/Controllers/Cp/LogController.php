<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Domain\Log\Models\LogEntry;
use Goldnead\WebhookManager\Repositories\LogRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Http\Controllers\CP\CpController;

class LogController extends CpController
{
    public function index(Request $request, LogRepository $repository)
    {
        abort_unless($request->user()?->can('view webhooks'), 403);

        $filters = $request->only(['level', 'type', 'correlation_id']);
        $logs = $repository->paginate(25, $filters);

        $rows = $logs->getCollection()->map(fn (LogEntry $log) => [
            'id' => $log->id,
            'uuid' => $log->uuid,
            'level' => $log->level,
            'type' => $log->type,
            'message' => $log->message,
            'created_at_human' => $log->created_at?->diffForHumans(),
            'context' => $log->context,
        ]);

        $payload = [
            'data' => $rows,
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('webhook-manager::Logs/Index', [
            'logs' => $payload,
            'filters' => $filters,
        ]);
    }
}
