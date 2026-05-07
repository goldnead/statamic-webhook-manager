<?php

namespace Goldnead\WebhookManager\Services\Inbound;

use Goldnead\WebhookManager\Mappers\MappingEngine;

class InboundMappingService
{
    public function __construct(protected MappingEngine $engine)
    {
    }

    /**
     * @return array{ok:bool, data:array, errors:array<int,string>}
     */
    public function map(?array $config, array $payload): array
    {
        if (empty($config)) {
            return ['ok' => true, 'data' => $payload, 'errors' => []];
        }
        return $this->engine->map($config, $payload);
    }
}
