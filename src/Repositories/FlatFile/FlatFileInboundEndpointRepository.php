<?php

namespace Goldnead\WebhookManager\Repositories\FlatFile;

use Goldnead\WebhookManager\Contracts\Repositories\InboundEndpointRepositoryInterface;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Goldnead\WebhookManager\Storage\AbstractFlatFileRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * YAML-backed inbound endpoint repository. Files live at
 * `inbound/{handle}.yaml` under the storage root.
 */
class FlatFileInboundEndpointRepository extends AbstractFlatFileRepository implements InboundEndpointRepositoryInterface
{
    protected function modelClass(): string
    {
        return InboundEndpoint::class;
    }

    protected function subdirectory(): string
    {
        return 'inbound';
    }

    public function find(int|string $id): ?InboundEndpoint
    {
        return $this->findRecord($id);
    }

    public function findByHandle(string $handle): ?InboundEndpoint
    {
        return $this->findByHandleRecord($handle);
    }

    public function findByUuid(string $uuid): ?InboundEndpoint
    {
        return $this->findByUuidRecord($uuid);
    }

    public function paginate(int $perPage = 25, ?string $search = null): LengthAwarePaginator
    {
        $items = $this->all()
            ->filter(fn (InboundEndpoint $e) => $this->matchesSearch($e, $search, ['name', 'handle', 'path']))
            ->values();

        return $this->paginateCollection($items, $perPage, (int) (request()->integer('page') ?: 1));
    }

    public function create(array $attributes): InboundEndpoint
    {
        return $this->createRecord($attributes);
    }

    public function save(InboundEndpoint $endpoint): InboundEndpoint
    {
        return $this->saveRecord($endpoint);
    }

    public function delete(InboundEndpoint $endpoint): bool
    {
        return $this->deleteRecord($endpoint);
    }
}
