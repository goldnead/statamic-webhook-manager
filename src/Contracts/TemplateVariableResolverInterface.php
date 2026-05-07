<?php

namespace Goldnead\WebhookManager\Contracts;

use Goldnead\WebhookManager\ValueObjects\ExecutionContext;

interface TemplateVariableResolverInterface
{
    /** Namespace handle, e.g. "entry", "system", "site". */
    public function namespace(): string;

    /**
     * Resolve `{{ namespace:key }}` to a scalar value.
     *
     * @return string|int|float|bool|null
     */
    public function resolve(string $key, ExecutionContext $context): mixed;
}
