<?php

namespace Goldnead\WebhookManager\Domain\Delivery\Actions;

use Goldnead\WebhookManager\Repositories\DeliveryRepository;
use Goldnead\WebhookManager\Repositories\LogRepository;

class PruneDeliveriesAction
{
    public function __construct(
        protected DeliveryRepository $deliveries,
        protected LogRepository $logs,
    ) {
    }

    /**
     * @return array{deliveries:int, logs:int}
     */
    public function __invoke(int $deliveryDays, int $logDays): array
    {
        $d = $deliveryDays > 0 ? $this->deliveries->pruneOlderThan($deliveryDays) : 0;
        $l = $logDays > 0 ? $this->logs->pruneOlderThan($logDays) : 0;
        return ['deliveries' => $d, 'logs' => $l];
    }
}
