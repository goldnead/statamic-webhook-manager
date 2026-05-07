<?php

namespace Goldnead\WebhookManager\Templates\Resolvers;

use Goldnead\WebhookManager\Contracts\TemplateVariableResolverInterface;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;

class EntryVariableResolver implements TemplateVariableResolverInterface
{
    public function namespace(): string
    {
        return 'entry';
    }

    public function resolve(string $key, ExecutionContext $context): mixed
    {
        if ($context->event->sourceType !== 'entry') {
            return null;
        }
        $payload = $context->payload();

        if (str_starts_with($key, 'data.')) {
            return self::dotGet($payload['data'] ?? [], substr($key, 5));
        }

        if (array_key_exists($key, $payload)) {
            return $payload[$key];
        }

        return self::dotGet($payload['data'] ?? [], $key);
    }

    private static function dotGet(array $array, string $key): mixed
    {
        if ($key === '') {
            return null;
        }
        $segments = explode('.', $key);
        $value = $array;
        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
                continue;
            }
            return null;
        }
        return $value;
    }
}
