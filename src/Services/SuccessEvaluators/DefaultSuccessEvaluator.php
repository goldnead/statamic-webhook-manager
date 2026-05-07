<?php

namespace Goldnead\WebhookManager\Services\SuccessEvaluators;

use Goldnead\WebhookManager\Contracts\SuccessEvaluatorInterface;

class DefaultSuccessEvaluator implements SuccessEvaluatorInterface
{
    public function handle(): string
    {
        return 'default';
    }

    public function isSuccess(array $response, array $config = []): bool
    {
        $status = (int) ($response['status'] ?? 0);
        return $status >= 200 && $status < 300;
    }
}
