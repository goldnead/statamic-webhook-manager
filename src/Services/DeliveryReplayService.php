<?php

namespace Goldnead\WebhookManager\Services;

use Goldnead\WebhookManager\Domain\Delivery\Actions\ReplayDeliveryAction;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Repositories\DeliveryRepository;

/**
 * Bulk replay coordinator.
 */
class DeliveryReplayService
{
    public function __construct(
        protected DeliveryRepository $repository,
        protected ReplayDeliveryAction $replay,
    ) {
    }

    public function replayOne(Delivery $delivery, bool $reRender = false): Delivery
    {
        return ($this->replay)($delivery, $reRender);
    }

    public function replayFailedSince(\DateTimeInterface $since, bool $reRender = false): int
    {
        $count = 0;
        foreach ($this->repository->failedSince($since) as $delivery) {
            ($this->replay)($delivery, $reRender);
            $count++;
        }
        return $count;
    }
}
