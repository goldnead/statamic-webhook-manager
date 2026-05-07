<?php

namespace Goldnead\WebhookManager\Services\SuccessEvaluators;

use Goldnead\WebhookManager\Contracts\SuccessEvaluatorInterface;

/**
 * Custom success matcher: pass a list of acceptable status codes plus an
 * optional response-body substring matcher.
 *
 * Config shape:
 *   { "status_codes": [200, 201, 202], "body_contains": "ok" }
 */
class StatusListSuccessEvaluator implements SuccessEvaluatorInterface
{
    public function handle(): string
    {
        return 'status_list';
    }

    public function isSuccess(array $response, array $config = []): bool
    {
        $status = (int) ($response['status'] ?? 0);
        $allowed = (array) ($config['status_codes'] ?? []);
        if ($allowed && ! in_array($status, $allowed, true)) {
            return false;
        }

        $needle = $config['body_contains'] ?? null;
        if ($needle && is_string($response['body'] ?? null)) {
            return str_contains($response['body'], $needle);
        }
        return $allowed !== [];
    }
}
