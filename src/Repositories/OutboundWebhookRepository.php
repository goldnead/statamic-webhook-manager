<?php

namespace Goldnead\WebhookManager\Repositories;

use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class OutboundWebhookRepository
{
    public function find(int $id): ?OutboundWebhook
    {
        return OutboundWebhook::find($id);
    }

    public function findByHandle(string $handle): ?OutboundWebhook
    {
        return OutboundWebhook::where('handle', $handle)->first();
    }

    public function findByUuid(string $uuid): ?OutboundWebhook
    {
        return OutboundWebhook::where('uuid', $uuid)->first();
    }

    /**
     * @return Collection<int, OutboundWebhook>
     */
    public function activeForTrigger(string $triggerHandle): Collection
    {
        return OutboundWebhook::query()
            ->where('enabled', true)
            ->where('trigger_type', $triggerHandle)
            ->get();
    }

    public function paginate(int $perPage = 25, ?string $search = null): LengthAwarePaginator
    {
        $query = OutboundWebhook::query()->orderBy('name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('handle', 'like', "%{$search}%")
                  ->orWhere('url', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function all(): Collection
    {
        return OutboundWebhook::orderBy('name')->get();
    }

    public function countActive(): int
    {
        return OutboundWebhook::where('enabled', true)->count();
    }
}
