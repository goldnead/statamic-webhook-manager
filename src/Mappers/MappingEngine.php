<?php

namespace Goldnead\WebhookManager\Mappers;

/**
 * Best-effort mapping engine. Inbound payloads are mapped to internal
 * structures via dot-notation paths plus optional defaults and transforms.
 *
 * TODO: REVIEW — first pass is intentionally JSON-config based. A richer
 * UI mapping builder is a v2 candidate; the engine itself can stay as is.
 */
class MappingEngine
{
    public function __construct(
        protected PathResolver $paths,
        protected ValueTransformer $transformer,
        protected TypeCoercer $coercer,
        protected DefaultValueResolver $defaults,
    ) {
    }

    /**
     * @param  array<string, array{path?:string, default?:mixed, transform?:string|array, type?:string, required?:bool}>  $mapping
     * @param  array  $payload  raw inbound payload
     * @return array{ok:bool, data:array, errors:array<int,string>}
     */
    public function map(array $mapping, array $payload): array
    {
        $out = [];
        $errors = [];
        foreach ($mapping as $targetKey => $rule) {
            $path = $rule['path'] ?? $targetKey;
            $value = $this->paths->get($payload, $path);

            if ($value === null && array_key_exists('default', $rule)) {
                $value = $this->defaults->resolve($rule['default']);
            }

            if ($value === null && ($rule['required'] ?? false)) {
                $errors[] = "Missing required value for '{$targetKey}' (path: {$path}).";
                continue;
            }

            if (! empty($rule['transform'])) {
                $value = $this->transformer->apply($rule['transform'], $value);
            }

            if (! empty($rule['type'])) {
                $value = $this->coercer->coerce($rule['type'], $value);
            }

            $out[$targetKey] = $value;
        }

        return ['ok' => $errors === [], 'data' => $out, 'errors' => $errors];
    }
}
