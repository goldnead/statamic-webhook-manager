<?php

namespace Goldnead\WebhookManager\Contracts;

use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\ExecutionResult;

interface ActionInterface
{
    public function handle(): string;

    public function label(): string;

    public function execute(array $config, ExecutionContext $context): ExecutionResult;
}
