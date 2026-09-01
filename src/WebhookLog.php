<?php

namespace Goldnead\WebhookManager;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Repositories\DeliveryRepository;
use Illuminate\Support\Collection;

/**
 * Read side of the delivery log for other addons.
 *
 * A payment, offer or funnel screen that wants to show "what did we send
 * about this object" asks here instead of querying `webhook_deliveries`
 * directly, so the table can change shape without breaking its readers.
 * Bound as the `webhook-manager.log` singleton; the {@see Facades\WebhookLog}
 * facade sits in front of it.
 */
class WebhookLog
{
    public function __construct(protected DeliveryRepository $deliveries) {}

    /**
     * Newest first.
     *
     * @return Collection<int, Delivery>
     */
    public function forSubject(string $type, int|string $id, int $limit = 50): Collection
    {
        return $this->deliveries->forSubject($type, $id, $limit);
    }

    public function countForSubject(string $type, int|string $id): int
    {
        return $this->deliveries->countForSubject($type, $id);
    }

    /**
     * The subject types the install knows about, from `webhook-manager.subjects`.
     *
     * @return list<string>
     */
    public function subjectTypes(): array
    {
        return array_values(array_map('strval', array_keys((array) config('webhook-manager.subjects', []))));
    }
}
