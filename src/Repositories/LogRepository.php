<?php

namespace Goldnead\WebhookManager\Repositories;

use Carbon\Carbon;
use Goldnead\WebhookManager\Domain\Log\Models\LogEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LogRepository
{
    public function paginate(int $perPage = 25, array $filters = []): LengthAwarePaginator
    {
        $q = LogEntry::query()->orderByDesc('created_at');

        if (! empty($filters['level'])) {
            $q->where('level', $filters['level']);
        }
        if (! empty($filters['type'])) {
            $q->where('type', $filters['type']);
        }
        if (! empty($filters['correlation_id'])) {
            $q->where('correlation_id', $filters['correlation_id']);
        }

        return $q->paginate($perPage)->withQueryString();
    }

    public function pruneOlderThan(int $days): int
    {
        $cutoff = Carbon::now()->subDays($days);

        return LogEntry::where('created_at', '<', $cutoff)->delete();
    }
}
