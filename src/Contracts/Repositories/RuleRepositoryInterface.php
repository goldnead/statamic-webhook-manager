<?php

namespace Goldnead\WebhookManager\Contracts\Repositories;

use Goldnead\WebhookManager\Domain\Rule\Models\Rule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface RuleRepositoryInterface
{
    public function find(int|string $id): ?Rule;

    public function findByHandle(string $handle): ?Rule;

    public function findByUuid(string $uuid): ?Rule;

    /** @return Collection<int, Rule> */
    public function activeForTrigger(string $triggerHandle): Collection;

    public function paginate(int $perPage = 25, ?string $search = null): LengthAwarePaginator;

    /** @return Collection<int, Rule> */
    public function all(): Collection;

    public function countActive(): int;

    /** @param array<string,mixed> $attributes */
    public function create(array $attributes): Rule;

    public function save(Rule $rule): Rule;

    public function delete(Rule $rule): bool;
}
