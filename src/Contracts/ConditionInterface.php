<?php

namespace Goldnead\WebhookManager\Contracts;

use Goldnead\WebhookManager\ValueObjects\ExecutionContext;

interface ConditionInterface
{
    public function handle(): string;

    public function label(): string;

    public function evaluate(array $config, ExecutionContext $context): bool;
}
