<?php

namespace Goldnead\WebhookManager\Repositories\FlatFile;

use Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Storage\AbstractFlatFileRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * YAML-backed outbound webhook repository. Files live at
 * `outbound/{handle}.yaml` under the storage root.
 */
class FlatFileOutboundWebhookRepository extends AbstractFlatFileRepository implements OutboundWebhookRepositoryInterface
{
    protected function modelClass(): string
    {
        return OutboundWebhook::class;
    }

    protected function subdirectory(): string
    {
        return 'outbound';
    }

    public function find(int|string $id): ?OutboundWebhook
    {
        return $this->findRecord($id);
    }

    public function findByHandle(string $handle): ?OutboundWebhook
    {
        return $this->findByHandleRecord($handle);
    }

    public function findByUuid(string $uuid): ?OutboundWebhook
    {
        return $this->findByUuidRecord($uuid);
    }

    /** @return Collection<int, OutboundWebhook> */
    public function activeForTrigger(string $triggerHandle): Collection
    {
        return $this->all()
            ->filter(fn (OutboundWebhook $h) => (bool) $h->enabled && $h->trigger_type === $triggerHandle)
            ->values();
    }

    public function paginate(int $perPage = 25, ?string $search = null): LengthAwarePaginator
    {
        $items = $this->all()
            ->filter(fn (OutboundWebhook $h) => $this->matchesSearch($h, $search, ['name', 'handle', 'url']))
            ->values();

        return $this->paginateCollection($items, $perPage, (int) (request()->integer('page') ?: 1));
    }

    public function create(array $attributes): OutboundWebhook
    {
        return $this->createRecord($attributes);
    }

    public function save(OutboundWebhook $hook): OutboundWebhook
    {
        return $this->saveRecord($hook);
    }

    public function delete(OutboundWebhook $hook): bool
    {
        return $this->deleteRecord($hook);
    }
}
