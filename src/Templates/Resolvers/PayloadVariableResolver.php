<?php

namespace Goldnead\WebhookManager\Templates\Resolvers;

use Goldnead\WebhookManager\Contracts\TemplateVariableResolverInterface;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;

/**
 * Generic resolver that exposes the entire trigger payload as nested
 * tokens, e.g. `{{ payload:contact.email }}`.
 */
class PayloadVariableResolver implements TemplateVariableResolverInterface
{
    public function namespace(): string
    {
        return 'payload';
    }

    public function resolve(string $key, ExecutionContext $context): mixed
    {
        return $context->field($key);
    }
}
