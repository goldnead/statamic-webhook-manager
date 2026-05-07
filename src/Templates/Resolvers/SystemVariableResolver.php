<?php

namespace Goldnead\WebhookManager\Templates\Resolvers;

use Goldnead\WebhookManager\Contracts\TemplateVariableResolverInterface;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;

class SystemVariableResolver implements TemplateVariableResolverInterface
{
    public function namespace(): string
    {
        return 'system';
    }

    public function resolve(string $key, ExecutionContext $context): mixed
    {
        $now = new \DateTimeImmutable();

        return match ($key) {
            'timestamp' => $now->getTimestamp(),
            'timestamp_iso' => $now->format(\DateTimeInterface::ATOM),
            'date' => $now->format('Y-m-d'),
            'datetime' => $now->format('Y-m-d H:i:s'),
            'correlation_id' => $context->event->correlationId,
            'trigger' => $context->event->triggerHandle,
            'replay' => $context->event->isReplay,
            default => null,
        };
    }
}
