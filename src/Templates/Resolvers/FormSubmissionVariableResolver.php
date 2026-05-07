<?php

namespace Goldnead\WebhookManager\Templates\Resolvers;

use Goldnead\WebhookManager\Contracts\TemplateVariableResolverInterface;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;

class FormSubmissionVariableResolver implements TemplateVariableResolverInterface
{
    public function namespace(): string
    {
        return 'form_submission';
    }

    public function resolve(string $key, ExecutionContext $context): mixed
    {
        if ($context->event->sourceType !== 'form_submission') {
            return null;
        }
        $payload = $context->payload();
        if (str_starts_with($key, 'data.')) {
            $data = $payload['data'] ?? [];
            return $data[substr($key, 5)] ?? null;
        }
        return $payload[$key] ?? ($payload['data'][$key] ?? null);
    }
}
