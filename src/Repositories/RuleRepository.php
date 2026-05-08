<?php

namespace Goldnead\WebhookManager\Repositories;

use Goldnead\WebhookManager\Domain\Rule\Models\Rule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class RuleRepository
{
    public function find(int $id): ?Rule
    {
        return Rule::find($id);
    }

    public function findByHandle(string $handle): ?Rule
    {
        return Rule::where('handle', $handle)->first();
    }

    public function findByUuid(string $uuid): ?Rule
    {
        return Rule::where('uuid', $uuid)->first();
    }

    public function activeForTrigger(string $triggerHandle): Collection
    {
        return Rule::where('enabled', true)
            ->where('trigger_type', $triggerHandle)
            ->orderBy('order_index')
            ->orderBy('id')
            ->get();
    }

    public function paginate(int $perPage = 25, ?string $search = null): LengthAwarePaginator
    {
        $query = Rule::query()->orderBy('order_index')->orderBy('name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('handle', 'like', "%{$search}%")
                  ->orWhere('trigger_type', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function all(): Collection
    {
        return Rule::orderBy('order_index')->orderBy('name')->get();
    }

    public function countActive(): int
    {
        return Rule::where('enabled', true)->count();
    }
}
