<?php

namespace Goldnead\WebhookManager\Templates\Resolvers;

use Goldnead\WebhookManager\Contracts\TemplateVariableResolverInterface;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;

class AssetVariableResolver implements TemplateVariableResolverInterface
{
    public function namespace(): string
    {
        return 'asset';
    }

    public function resolve(string $key, ExecutionContext $context): mixed
    {
        if ($context->event->sourceType !== 'asset') {
            return null;
        }
        return $context->payload()[$key] ?? null;
    }
}
