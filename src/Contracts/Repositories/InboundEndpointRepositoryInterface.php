<?php

namespace Goldnead\WebhookManager\Contracts\Repositories;

use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface InboundEndpointRepositoryInterface
{
    public function find(int|string $id): ?InboundEndpoint;

    public function findByHandle(string $handle): ?InboundEndpoint;

    public function findByUuid(string $uuid): ?InboundEndpoint;

    public function paginate(int $perPage = 25, ?string $search = null): LengthAwarePaginator;

    /** @return Collection<int, InboundEndpoint> */
    public function all(): Collection;

    public function countActive(): int;

    /** @param array<string,mixed> $attributes */
    public function create(array $attributes): InboundEndpoint;

    public function save(InboundEndpoint $endpoint): InboundEndpoint;

    public function delete(InboundEndpoint $endpoint): bool;
}
