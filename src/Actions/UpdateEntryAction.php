<?php

namespace Goldnead\WebhookManager\Actions;

use Goldnead\WebhookManager\Contracts\ActionInterface;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\ExecutionResult;
use Statamic\Facades\Entry;

/**
 * Update an existing entry, located by id (preferred) or by lookup field
 * within a collection.
 *
 * Rule config:
 *   - `lookup_field` (string, optional, default `id`) — payload key with the lookup value
 *   - `collection` (string, optional but recommended for non-id lookups)
 *   - `data` (object/array, optional) — explicit values; defaults to the trigger payload
 *
 * Reserved keys (`id`, `slug`, `site`, `published`) are removed from data
 * before merging.
 */
class UpdateEntryAction implements ActionInterface
{
    public function handle(): string
    {
        return 'update_entry';
    }

    public function label(): string
    {
        return 'Update entry';
    }

    public function execute(array $config, ExecutionContext $context): ExecutionResult
    {
        $lookupField = (string) ($config['lookup_field'] ?? 'id');
        $collection = (string) ($config['collection'] ?? '');
        $data = is_array($config['data'] ?? null) ? $config['data'] : $context->payload();
        $lookupValue = $data[$lookupField] ?? $context->field($lookupField);

        if ($lookupValue === null || $lookupValue === '') {
            return ExecutionResult::fail("Missing lookup value '{$lookupField}'.");
        }

        try {
            $entry = $this->resolve($lookupField, (string) $lookupValue, $collection);
            if (! $entry) {
                return ExecutionResult::fail('Entry not found.', [
                    'lookup_field' => $lookupField,
                    'lookup_value' => $lookupValue,
                ]);
            }

            $merged = $data;
            unset($merged['id'], $merged['slug'], $merged['site'], $merged['published']);

            $entry->data(array_merge($entry->data()->all(), $merged));

            if (array_key_exists('published', $data)) {
                $entry->published((bool) $data['published']);
            }
            if (! empty($data['slug']) && is_string($data['slug'])) {
                $entry->slug($data['slug']);
            }
            $entry->save();

            return ExecutionResult::ok('Entry updated.', [
                'id' => $entry->id(),
                'slug' => $entry->slug(),
                'collection' => $entry->collectionHandle(),
            ]);
        } catch (\Throwable $e) {
            return ExecutionResult::fail('Failed to update entry: '.$e->getMessage());
        }
    }

    protected function resolve(string $lookupField, string $value, string $collection)
    {
        if ($lookupField === 'id') {
            return Entry::find($value);
        }

        $query = Entry::query()->where($lookupField, $value);
        if ($collection !== '') {
            $query->where('collection', $collection);
        }
        return $query->first();
    }
}
