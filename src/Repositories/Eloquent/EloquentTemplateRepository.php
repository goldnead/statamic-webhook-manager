<?php

namespace Goldnead\WebhookManager\Repositories\Eloquent;

use Goldnead\WebhookManager\Contracts\Repositories\TemplateRepositoryInterface;
use Goldnead\WebhookManager\Domain\Template\Models\Template;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentTemplateRepository implements TemplateRepositoryInterface
{
    public function find(int|string $id): ?Template
    {
        return Template::find($id);
    }

    public function findByHandle(string $handle): ?Template
    {
        return Template::where('handle', $handle)->first();
    }

    public function findByUuid(string $uuid): ?Template
    {
        return Template::where('uuid', $uuid)->first();
    }

    /** @return Collection<int, Template> */
    public function ofType(string $type): Collection
    {
        return Template::where('type', $type)->orderBy('name')->get();
    }

    public function paginate(int $perPage = 25, ?string $search = null, ?string $type = null): LengthAwarePaginator
    {
        $query = Template::query()->orderBy('name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('handle', 'like', "%{$search}%");
            });
        }
        if ($type) {
            $query->where('type', $type);
        }

        return $query->paginate($perPage);
    }

    /** @return Collection<int, Template> */
    public function all(): Collection
    {
        return Template::orderBy('name')->get();
    }

    public function create(array $attributes): Template
    {
        $template = new Template;
        $template->fill($attributes);
        $template->save();

        return $template->fresh();
    }

    public function save(Template $template): Template
    {
        $template->save();

        return $template->fresh();
    }

    public function delete(Template $template): bool
    {
        return (bool) $template->delete();
    }
}
