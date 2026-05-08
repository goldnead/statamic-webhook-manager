<?php

namespace Goldnead\WebhookManager\Actions;

use Goldnead\WebhookManager\Contracts\ActionInterface;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\ExecutionResult;
use Statamic\Facades\Entry;

/**
 * Set a single field on an existing entry to a static or dynamic value.
 *
 * Rule config:
 *   - `lookup_field` (string, optional, default `id`) — payload key with lookup value
 *   - `collection` (string, optional but recommended for non-id lookups)
 *   - `field` (string, required) — entry field to set
 *   - `value` (any, optional) — literal value
 *   - `from_path` (string, optional) — dot path into trigger payload to source the value
 *
 * Either `value` or `from_path` must be provided. `from_path` wins if both are given.
 */
class SetFieldValueAction implements ActionInterface
{
    public function handle(): string
    {
        return 'set_field_value';
    }

    public function label(): string
    {
        return 'Set entry field value';
    }

    public function execute(array $config, ExecutionContext $context): ExecutionResult
    {
        $field = (string) ($config['field'] ?? '');
        if ($field === '') {
            return ExecutionResult::fail('Missing required config.field.');
        }

        $lookupField = (string) ($config['lookup_field'] ?? 'id');
        $collection = (string) ($config['collection'] ?? '');
        $lookupValue = $context->field($lookupField);

        if ($lookupValue === null || $lookupValue === '') {
            return ExecutionResult::fail("Missing lookup value '{$lookupField}'.");
        }

        $value = isset($config['from_path']) && is_string($config['from_path'])
            ? $context->field($config['from_path'])
            : ($config['value'] ?? null);

        try {
            $entry = $lookupField === 'id'
                ? Entry::find((string) $lookupValue)
                : Entry::query()
                    ->where($lookupField, $lookupValue)
                    ->when($collection, fn ($q) => $q->where('collection', $collection))
                    ->first();

            if (! $entry) {
                return ExecutionResult::fail('Entry not found.', [
                    'lookup_field' => $lookupField,
                    'lookup_value' => $lookupValue,
                ]);
            }

            $entry->data(array_merge($entry->data()->all(), [$field => $value]));
            $entry->save();

            return ExecutionResult::ok('Field set.', [
                'id' => $entry->id(),
                'field' => $field,
            ]);
        } catch (\Throwable $e) {
            return ExecutionResult::fail("Failed to set field '{$field}': ".$e->getMessage());
        }
    }
}
