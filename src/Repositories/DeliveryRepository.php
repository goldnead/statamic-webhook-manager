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
     * @param  array{status?:string, webhook_id?:int, trigger?:string, error_type?:string, from?:string, to?:string}  $filters
     */
    public function paginate(int $perPage = 25, ?string $search = null, array $filters = []): LengthAwarePaginator
    {
        $q = $this->buildQuery($filters)->orderByDesc('created_at');

        if ($search !== null && trim($search) !== '') {
            $needle = '%'.trim($search).'%';
            $q->where(function ($where) use ($needle) {
                $where->where('request_url', 'like', $needle)
                    ->orWhere('correlation_id', 'like', $needle)
                    ->orWhere('trigger_reference', 'like', $needle);
            });
        }

        return $q->paginate($perPage)->withQueryString();
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

        return $q;
    }
}
