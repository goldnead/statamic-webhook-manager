<?php

namespace Goldnead\WebhookManager\Templates\Resolvers;

use Goldnead\WebhookManager\Contracts\TemplateVariableResolverInterface;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;

class SiteVariableResolver implements TemplateVariableResolverInterface
{
    public function namespace(): string
    {
        return 'site';
    }

    public function resolve(string $key, ExecutionContext $context): mixed
    {
        return match ($key) {
            'handle' => $context->event->site,
            'locale' => $context->event->locale,
            default => null,
        };
    }
}
