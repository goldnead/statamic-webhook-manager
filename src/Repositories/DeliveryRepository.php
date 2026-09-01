<?php

namespace Goldnead\WebhookManager\Repositories;

use Carbon\Carbon;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DeliveryRepository
{
    public function find(int $id): ?Delivery
    {
        return Delivery::find($id);
    }

    public function findByUuid(string $uuid): ?Delivery
    {
        return Delivery::where('uuid', $uuid)->first();
    }

    /**
     * Paginated delivery listing with optional full-text search and filters.
     *
     * The search/filters split mirrors LogRepository so both listings have
     * the same shape from the controller's perspective.
     *
     * @param  array{status?:string, webhook_id?:int, trigger?:string, error_type?:string, from?:string, to?:string, subject_type?:string, subject_id?:string|int}  $filters
     */
    public function paginate(int $perPage = 25, ?string $search = null, array $filters = []): LengthAwarePaginator
    {
        $q = $this->buildQuery($filters)->orderByDesc('created_at');

        if ($search !== null && trim($search) !== '') {
            $needle = '%'.trim($search).'%';
            $q->where(function ($where) use ($needle) {
                $where->where('request_url', 'like', $needle)
                    ->orWhere('correlation_id', 'like', $needle)
                    ->orWhere('trigger_reference', 'like', $needle)
                    ->orWhere('subject_id', 'like', $needle);
            });
        }

        return $q->paginate($perPage)->withQueryString();
    }

    /**
     * Every delivery recorded about one object, newest first.
     *
     * `subject_id` is a string column: an integer id is compared as its
     * string form, so `forSubject('payment', 77)` and `('payment', '77')`
     * read the same rows.
     *
     * @return Collection<int, Delivery>
     */
    public function forSubject(string $type, int|string $id, int $limit = 50): Collection
    {
        return $this->subjectQuery($type, $id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function countForSubject(string $type, int|string $id): int
    {
        return $this->subjectQuery($type, $id)->count();
    }

    /**
     * Distinct subject types present in the log, for the listing's filter.
     *
     * @return list<string>
     */
    public function subjectTypesInUse(): array
    {
        return Delivery::query()
            ->whereNotNull('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->map(fn ($type) => (string) $type)
            ->values()
            ->all();
    }

    /**
     * @return Builder<Delivery>
     */
    protected function subjectQuery(string $type, int|string $id): Builder
    {
        return Delivery::query()
            ->where('subject_type', $type)
            ->where('subject_id', (string) $id);
    }

    /** @return Collection<int, Delivery> */
    public function failedSince(\DateTimeInterface $since): Collection
    {
        return Delivery::query()
            ->where('status', Delivery::STATUS_FAILED)
            ->where('created_at', '>=', $since)
            ->get();
    }

    public function readyForRetry(?\DateTimeInterface $now = null): Collection
    {
        $now ??= Carbon::now();

        return Delivery::query()
            ->whereIn('status', [Delivery::STATUS_FAILED, Delivery::STATUS_PROCESSING])
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', $now)
            ->get();
    }

    public function counts(): array
    {
        return [
            'success' => Delivery::where('status', Delivery::STATUS_SUCCESS)->count(),
            'failed' => Delivery::where('status', Delivery::STATUS_FAILED)->count(),
            'pending' => Delivery::whereIn('status', [
                Delivery::STATUS_PENDING,
                Delivery::STATUS_PROCESSING,
            ])->count(),
        ];
    }

    public function successRate(int $hours = 24): float
    {
        $since = Carbon::now()->subHours($hours);

        $total = Delivery::where('created_at', '>=', $since)->count();
        if ($total === 0) {
            return 0.0;
        }
        $success = Delivery::where('created_at', '>=', $since)
            ->where('status', Delivery::STATUS_SUCCESS)
            ->count();

        return round(($success / $total) * 100, 2);
    }

    public function pruneOlderThan(int $days): int
    {
        $cutoff = Carbon::now()->subDays($days);

        return Delivery::where('created_at', '<', $cutoff)->delete();
    }

    protected function buildQuery(array $filters): Builder
    {
        $q = Delivery::query();

        if (! empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (! empty($filters['webhook_id'])) {
            $q->where('outbound_webhook_id', $filters['webhook_id']);
        }
        if (! empty($filters['trigger'])) {
            $q->where('trigger_type', $filters['trigger']);
        }
        if (! empty($filters['error_type'])) {
            $q->where('error_type', $filters['error_type']);
        }
        if (! empty($filters['from'])) {
            $q->where('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $q->where('created_at', '<=', $filters['to']);
        }
        if (! empty($filters['subject_type'])) {
            $q->where('subject_type', (string) $filters['subject_type']);
        }
        if (isset($filters['subject_id']) && $filters['subject_id'] !== '') {
            $q->where('subject_id', (string) $filters['subject_id']);
        }

        return $q;
    }
}
