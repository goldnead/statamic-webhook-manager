<?php

namespace Goldnead\WebhookManager\Contracts\Repositories;

use Goldnead\WebhookManager\Domain\Template\Models\Template;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TemplateRepositoryInterface
{
    public function find(int|string $id): ?Template;

    public function findByHandle(string $handle): ?Template;

    public function findByUuid(string $uuid): ?Template;

    /** @return Collection<int, Template> */
    public function ofType(string $type): Collection;

    public function paginate(int $perPage = 25, ?string $search = null, ?string $type = null): LengthAwarePaginator;

    /** @return Collection<int, Template> */
    public function all(): Collection;

    /** @param array<string,mixed> $attributes */
    public function create(array $attributes): Template;

    public function save(Template $template): Template;

    public function delete(Template $template): bool;
}
