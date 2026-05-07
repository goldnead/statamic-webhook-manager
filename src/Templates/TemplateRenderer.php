<?php

namespace Goldnead\WebhookManager\Templates;

use Goldnead\WebhookManager\Contracts\PayloadRendererInterface;
use Goldnead\WebhookManager\Registries\VariableResolverRegistry;
use Goldnead\WebhookManager\Templates\Exceptions\TemplateRenderException;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;

/**
 * Token-based template renderer.
 *
 * Syntax: `{{ namespace:key }}` — namespace is resolved against the
 * VariableResolverRegistry, key is passed to the resolver.
 *
 * Optional default fallback: `{{ entry:title|default('untitled') }}`.
 *
 * TODO: REVIEW — keeping the syntax intentionally narrow. Supporting full
 * Antlers would significantly expand the attack surface (arbitrary code
 * execution if a malicious user crafts a template). A small, auditable
 * mini-language is the right tradeoff for v1.
 */
class TemplateRenderer implements PayloadRendererInterface
{
    public const TOKEN_PATTERN = '/\{\{\s*([a-zA-Z0-9_]+)\s*:\s*([a-zA-Z0-9_.-]+)\s*(?:\|\s*default\(\s*\'([^\']*)\'\s*\)\s*)?\}\}/';

    public function __construct(protected VariableResolverRegistry $resolvers)
    {
    }

    public function render(string $template, ExecutionContext $context): string
    {
        return preg_replace_callback(self::TOKEN_PATTERN, function (array $matches) use ($context, $template) {
            [, $namespace, $key, $default] = $matches + [3 => null];

            $resolver = $this->resolvers->get($namespace);
            if (! $resolver) {
                if ($default !== null) {
                    return $default;
                }
                throw new TemplateRenderException("Unknown template namespace: {$namespace}");
            }

            $value = $resolver->resolve($key, $context);
            if ($value === null) {
                if ($default !== null) {
                    return $default;
                }
                return '';
            }
            return self::stringify($value);
        }, $template) ?? $template;
    }

    public function validate(string $template): array
    {
        $issues = [];
        if (preg_match_all(self::TOKEN_PATTERN, $template, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $namespace = $m[1];
                if (! $this->resolvers->get($namespace)) {
                    $issues[] = "Unknown namespace: {$namespace}";
                }
            }
        }
        // Bare `{{ ... }}` that didn't match the strict syntax pattern.
        if (preg_match_all('/\{\{[^}]*\}\}/', $template, $allMatches)
            && (preg_match_all(self::TOKEN_PATTERN, $template, $strict) < count($allMatches[0]))) {
            $issues[] = 'Some tokens do not match the supported {{ namespace:key }} syntax.';
        }
        return $issues;
    }

    private static function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value) || is_object($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        return (string) $value;
    }
}
