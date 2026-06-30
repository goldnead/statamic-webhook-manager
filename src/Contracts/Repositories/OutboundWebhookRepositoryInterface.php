<?php

namespace Goldnead\WebhookManager\Contracts\Repositories;

use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Storage-driver-agnostic contract for outbound webhook persistence.
 *
 * Implemented by both the Eloquent (database) and FlatFile (YAML) drivers;
 * the active implementation is bound in the service provider based on
 * `webhook-manager.storage.driver`.
 */
interface OutboundWebhookRepositoryInterface
{
    public function find(int|string $id): ?OutboundWebhook;

    public function findByHandle(string $handle): ?OutboundWebhook;

    public function findByUuid(string $uuid): ?OutboundWebhook;

    /** @return Collection<int, OutboundWebhook> */
    public function activeForTrigger(string $triggerHandle): Collection;

    public function paginate(int $perPage = 25, ?string $search = null): LengthAwarePaginator;

    /** @return Collection<int, OutboundWebhook> */
    public function all(): Collection;

    public function countActive(): int;

    /** @param array<string,mixed> $attributes */
    public function create(array $attributes): OutboundWebhook;

    public function save(OutboundWebhook $hook): OutboundWebhook;

    public function delete(OutboundWebhook $hook): bool;
}
