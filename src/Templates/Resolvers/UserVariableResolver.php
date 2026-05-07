<?php

namespace Goldnead\WebhookManager\Templates\Resolvers;

use Goldnead\WebhookManager\Contracts\TemplateVariableResolverInterface;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;

class UserVariableResolver implements TemplateVariableResolverInterface
{
    public function namespace(): string
    {
        return 'user';
    }

    public function resolve(string $key, ExecutionContext $context): mixed
    {
        if ($context->event->sourceType !== 'user') {
            return null;
        }
        return $context->payload()[$key] ?? null;
    }
}
