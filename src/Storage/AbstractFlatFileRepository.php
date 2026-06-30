<?php

namespace Goldnead\WebhookManager\Storage;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Shared CRUD for the flat-file config repositories.
 *
 * Each config entity (outbound webhook, inbound endpoint, rule, template)
 * is stored as one YAML file named after its handle, e.g.
 * `outbound/notify-slack.yaml`, under the storage root. Records keep a
 * stable integer `id` (assigned as max+1 on create and persisted in the
 * file) so the database-backed delivery/log tables can keep referencing a
 * webhook by the same integer key under either storage driver.
 *
 * Volumes here are small (config, not telemetry), so reads glob + parse
 * the directory directly — no separate on-disk index is needed.
 *
 * The generic CRUD is exposed as protected `*Record` methods returning the
 * base Model type; each concrete repository re-declares the narrowly-typed
 * interface methods (returning its own model) that simply delegate here.
 * This keeps the entity-specific contracts strict without running into
 * PHP's parameter-contravariance rules on a shared base.
 */
abstract class AbstractFlatFileRepository
{
    public function __construct(
        protected FileStore $files,
        protected ModelHydrator $hydrator,
    ) {
    }

    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    /** Sub-directory under the storage root, e.g. "outbound". */
    abstract protected function subdirectory(): string;

    /**
     * Every stored record, hydrated and sorted by name.
     *
     * @return Collection<int, Model>
     */
    public function all(): Collection
    {
        return collect($this->files->glob($this->subdirectory().'/*.yaml'))
            ->map(fn (string $relative) => $this->files->readYaml($relative))
            ->filter(fn (array $data) => ! empty($data['id']))
            ->map(fn (array $data) => $this->hydrator->fromStorage($this->modelClass(), $data))
            ->sortBy(fn (Model $m) => mb_strtolower((string) ($m->name ?? '')))
            ->values();
    }

    public function countActive(): int
    {
        return $this->all()->filter(fn (Model $m) => (bool) ($m->enabled ?? true))->count();
    }

    protected function findRecord(int|string $id): ?Model
    {
        return $this->all()->first(fn (Model $m) => (string) $m->id === (string) $id);
    }

    protected function findByHandleRecord(string $handle): ?Model
    {
        $data = $this->files->readYaml($this->relativePath($handle));

        return empty($data['id'])
            ? $this->all()->first(fn (Model $m) => $m->handle === $handle)
            : $this->hydrator->fromStorage($this->modelClass(), $data);
    }

    protected function findByUuidRecord(string $uuid): ?Model
    {
        return $this->all()->first(fn (Model $m) => $m->uuid === $uuid);
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    protected function createRecord(array $attributes): Model
    {
        $class = $this->modelClass();
        /** @var Model $model */
        $model = new $class;
        $model->fill($attributes);

        if (empty($model->handle)) {
            $model->handle = Str::slug((string) ($model->name ?? Str::random(8)));
        }
        $model->handle = $this->uniqueHandle((string) $model->handle);

        $model->id = $this->nextId();
        if (empty($model->uuid)) {
            $model->uuid = (string) Str::uuid();
        }
        $this->stampTimestamps($model, creating: true);

        $this->files->writeYaml($this->relativePath((string) $model->handle), $this->hydrator->toStorage($model));

        return $this->findRecord($model->id);
    }

    protected function saveRecord(Model $model): Model
    {
        if (empty($model->id)) {
            return $this->createRecord($model->getAttributes());
        }

        // A handle change renames the file — delete the stale one first.
        foreach ($this->files->glob($this->subdirectory().'/*.yaml') as $relative) {
            $data = $this->files->readYaml($relative);
            if (($data['id'] ?? null) == $model->id && $relative !== $this->relativePath((string) $model->handle)) {
                $this->files->delete($relative);
            }
        }

        $this->stampTimestamps($model, creating: false);
        $this->files->writeYaml($this->relativePath((string) $model->handle), $this->hydrator->toStorage($model));

        return $this->findRecord($model->id);
    }

    protected function deleteRecord(Model $model): bool
    {
        $deleted = false;
        foreach ($this->files->glob($this->subdirectory().'/*.yaml') as $relative) {
            $data = $this->files->readYaml($relative);
            if (($data['id'] ?? null) == $model->id) {
                $deleted = $this->files->delete($relative) || $deleted;
            }
        }

        return $deleted;
    }

    /**
     * @param  Collection<int, Model>  $items
     */
    protected function paginateCollection(Collection $items, int $perPage, int $page = 1): LengthAwarePaginator
    {
        $page = max(1, $page);

        return new LengthAwarePaginator(
            $items->slice(($page - 1) * $perPage, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => 'page'],
        );
    }

    protected function nextId(): int
    {
        $max = collect($this->files->glob($this->subdirectory().'/*.yaml'))
            ->map(fn (string $relative) => (int) ($this->files->readYaml($relative)['id'] ?? 0))
            ->max();

        return (int) $max + 1;
    }

    protected function uniqueHandle(string $handle, ?int $ignoreId = null): string
    {
        $existing = $this->all()
            ->reject(fn (Model $m) => $ignoreId !== null && (int) $m->id === $ignoreId)
            ->map(fn (Model $m) => $m->handle)
            ->all();

        $candidate = $handle;
        $i = 2;
        while (in_array($candidate, $existing, true)) {
            $candidate = $handle.'-'.$i++;
        }

        return $candidate;
    }

    protected function stampTimestamps(Model $model, bool $creating): void
    {
        $now = now()->toIso8601String();
        if ($creating && empty($model->created_at)) {
            $model->setAttribute('created_at', $now);
        }
        $model->setAttribute('updated_at', $now);
    }

    protected function relativePath(string $handle): string
    {
        return $this->subdirectory().'/'.$handle.'.yaml';
    }

    /**
     * Case-insensitive substring match across the given attributes.
     *
     * @param  array<int,string>  $fields
     */
    protected function matchesSearch(Model $model, ?string $search, array $fields): bool
    {
        if ($search === null || trim($search) === '') {
            return true;
        }
        $needle = mb_strtolower(trim($search));
        foreach ($fields as $field) {
            if (str_contains(mb_strtolower((string) ($model->{$field} ?? '')), $needle)) {
                return true;
            }
        }

        return false;
    }
}
