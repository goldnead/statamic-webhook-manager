<?php

namespace Goldnead\WebhookManager\Repositories;

use Carbon\Carbon;
use Goldnead\WebhookManager\Domain\Log\Models\LogEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LogRepository
{
    /**
     * Paginated log listing with optional full-text search and filters.
     *
     * The `type` filter maps to the DB column `type` (the UI calls it
     * "error_type" — the controller is responsible for the rename so
     * the repository stays unaware of UI vocabulary).
     *
     * @param  array{level?:string, type?:string, correlation_id?:string, from?:string, to?:string}  $filters
     */
    public function paginate(int $perPage = 25, ?string $search = null, array $filters = []): LengthAwarePaginator
    {
        $q = LogEntry::query()->orderByDesc('created_at');

        if ($search !== null && trim($search) !== '') {
            $needle = '%'.trim($search).'%';
            $q->where(function ($where) use ($needle) {
                $where->where('message', 'like', $needle)
                    ->orWhere('correlation_id', 'like', $needle);
            });
        }

        if (! empty($filters['level'])) {
            $q->where('level', $filters['level']);
        }
        if (! empty($filters['type'])) {
            $q->where('type', $filters['type']);
        }
        if (! empty($filters['correlation_id'])) {
            // Partial match on correlation IDs — useful when only the
            // delivery prefix is known.
            $q->where('correlation_id', 'like', '%'.$filters['correlation_id'].'%');
        }
        if (! empty($filters['from'])) {
            $q->where('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $q->where('created_at', '<=', $filters['to']);
        }

        return $q->paginate($perPage)->withQueryString();
    }

    public function pruneOlderThan(int $days): int
    {
        $cutoff = Carbon::now()->subDays($days);

        return LogEntry::where('created_at', '<', $cutoff)->delete();
    }
}
