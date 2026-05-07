<?php

namespace Goldnead\WebhookManager\Repositories;

use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class InboundEndpointRepository
{
    public function find(int $id): ?InboundEndpoint
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

    public function all(): Collection
    {
        return InboundEndpoint::orderBy('name')->get();
    }

    public function countActive(): int
    {
        return InboundEndpoint::where('enabled', true)->count();
    }
}
