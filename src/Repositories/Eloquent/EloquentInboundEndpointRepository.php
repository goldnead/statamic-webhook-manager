<?php

namespace Goldnead\WebhookManager\Repositories\Eloquent;

use Goldnead\WebhookManager\Contracts\Repositories\InboundEndpointRepositoryInterface;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentInboundEndpointRepository implements InboundEndpointRepositoryInterface
{
    public function find(int|string $id): ?InboundEndpoint
    {
        return InboundEndpoint::find($id);
    }

    public function findByHandle(string $handle): ?InboundEndpoint
    {
        return InboundEndpoint::where('handle', $handle)->first();
    }

    public function findByUuid(string $uuid): ?InboundEndpoint
    {
        return InboundEndpoint::where('uuid', $uuid)->first();
    }

    public function paginate(int $perPage = 25, ?string $search = null): LengthAwarePaginator
    {
        $query = InboundEndpoint::query()->orderBy('name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('handle', 'like', "%{$search}%")
                    ->orWhere('path', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    /** @return Collection<int, InboundEndpoint> */
    public function all(): Collection
    {
        return InboundEndpoint::orderBy('name')->get();
    }

    public function countActive(): int
    {
        return InboundEndpoint::where('enabled', true)->count();
    }

    public function create(array $attributes): InboundEndpoint
    {
        $endpoint = new InboundEndpoint;
        $endpoint->fill($attributes);
        $endpoint->save();

        return $endpoint->fresh();
    }

    public function save(InboundEndpoint $endpoint): InboundEndpoint
    {
        $endpoint->save();

        return $endpoint->fresh();
    }

    public function delete(InboundEndpoint $endpoint): bool
    {
        return (bool) $endpoint->delete();
    }
}
