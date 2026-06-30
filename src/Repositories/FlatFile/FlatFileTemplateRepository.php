<?php

namespace Goldnead\WebhookManager\Repositories\FlatFile;

use Goldnead\WebhookManager\Contracts\Repositories\TemplateRepositoryInterface;
use Goldnead\WebhookManager\Domain\Template\Models\Template;
use Goldnead\WebhookManager\Storage\AbstractFlatFileRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * YAML-backed template repository. Files live at
 * `templates/{handle}.yaml` under the storage root.
 */
class FlatFileTemplateRepository extends AbstractFlatFileRepository implements TemplateRepositoryInterface
{
    protected function modelClass(): string
    {
        return Template::class;
    }

    protected function subdirectory(): string
    {
        return 'templates';
    }

    public function find(int|string $id): ?Template
    {
        return $this->findRecord($id);
    }

    public function findByHandle(string $handle): ?Template
    {
        return $this->findByHandleRecord($handle);
    }

    public function findByUuid(string $uuid): ?Template
    {
        return $this->findByUuidRecord($uuid);
    }

    /** @return Collection<int, Template> */
    public function ofType(string $type): Collection
    {
        return $this->all()
            ->filter(fn (Template $t) => $t->type === $type)
            ->values();
    }

    public function paginate(int $perPage = 25, ?string $search = null, ?string $type = null): LengthAwarePaginator
    {
        $items = $this->all()
            ->filter(fn (Template $t) => $this->matchesSearch($t, $search, ['name', 'handle']))
            ->filter(fn (Template $t) => $type === null || $t->type === $type)
            ->values();

        return $this->paginateCollection($items, $perPage, (int) (request()->integer('page') ?: 1));
    }

    public function create(array $attributes): Template
    {
        return $this->createRecord($attributes);
    }

    public function save(Template $template): Template
    {
        return $this->saveRecord($template);
    }

    public function delete(Template $template): bool
    {
        return $this->deleteRecord($template);
    }
}
