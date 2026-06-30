<?php

namespace Goldnead\WebhookManager\Repositories\FlatFile;

use Goldnead\WebhookManager\Contracts\Repositories\RuleRepositoryInterface;
use Goldnead\WebhookManager\Domain\Rule\Models\Rule;
use Goldnead\WebhookManager\Storage\AbstractFlatFileRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * YAML-backed rule repository. Files live at `rules/{handle}.yaml` under
 * the storage root. Ordering follows order_index then name to mirror the
 * Eloquent driver.
 */
class FlatFileRuleRepository extends AbstractFlatFileRepository implements RuleRepositoryInterface
{
    protected function modelClass(): string
    {
        return Rule::class;
    }

    protected function subdirectory(): string
    {
        return 'rules';
    }

    public function all(): Collection
    {
        return parent::all()
            ->sortBy([
                fn (Rule $a, Rule $b) => ((int) $a->order_index) <=> ((int) $b->order_index),
                fn (Rule $a, Rule $b) => mb_strtolower((string) $a->name) <=> mb_strtolower((string) $b->name),
            ])
            ->values();
    }

    public function find(int|string $id): ?Rule
    {
        return $this->findRecord($id);
    }

    public function findByHandle(string $handle): ?Rule
    {
        return $this->findByHandleRecord($handle);
    }

    public function findByUuid(string $uuid): ?Rule
    {
        return $this->findByUuidRecord($uuid);
    }

    /** @return Collection<int, Rule> */
    public function activeForTrigger(string $triggerHandle): Collection
    {
        return $this->all()
            ->filter(fn (Rule $r) => (bool) $r->enabled && $r->trigger_type === $triggerHandle)
            ->values();
    }

    public function paginate(int $perPage = 25, ?string $search = null): LengthAwarePaginator
    {
        $items = $this->all()
            ->filter(fn (Rule $r) => $this->matchesSearch($r, $search, ['name', 'handle', 'trigger_type']))
            ->values();

        return $this->paginateCollection($items, $perPage, (int) (request()->integer('page') ?: 1));
    }

    public function create(array $attributes): Rule
    {
        return $this->createRecord($attributes);
    }

    public function save(Rule $rule): Rule
    {
        return $this->saveRecord($rule);
    }

    public function delete(Rule $rule): bool
    {
        return $this->deleteRecord($rule);
    }
}
