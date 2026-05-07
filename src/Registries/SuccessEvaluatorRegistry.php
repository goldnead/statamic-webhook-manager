<?php

namespace Goldnead\WebhookManager\Registries;

use Goldnead\WebhookManager\Contracts\SuccessEvaluatorInterface;
use Goldnead\WebhookManager\Services\SuccessEvaluators\DefaultSuccessEvaluator;
use Goldnead\WebhookManager\Services\SuccessEvaluators\StatusListSuccessEvaluator;

class SuccessEvaluatorRegistry
{
    /** @var array<string, SuccessEvaluatorInterface> */
    protected array $evaluators = [];

    public function register(SuccessEvaluatorInterface $evaluator): void
    {
        $this->evaluators[$evaluator->handle()] = $evaluator;
    }

    public function get(string $handle): ?SuccessEvaluatorInterface
    {
        return $this->evaluators[$handle] ?? null;
    }

    public function default(): SuccessEvaluatorInterface
    {
        return $this->get('default') ?? new DefaultSuccessEvaluator();
    }

    public function registerDefaults(): void
    {
        $this->register(new DefaultSuccessEvaluator());
        $this->register(new StatusListSuccessEvaluator());
    }
}
