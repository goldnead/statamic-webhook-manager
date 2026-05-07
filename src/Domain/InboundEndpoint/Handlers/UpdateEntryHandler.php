<?php

namespace Goldnead\WebhookManager\Domain\InboundEndpoint\Handlers;

use Goldnead\WebhookManager\Contracts\InboundActionHandlerInterface;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Statamic\Facades\Entry;

/**
 * Updates an existing entry, located by id (preferred) or by slug+collection.
 *
 * Endpoint `action_config` must include:
 *   - `collection` (string, optional but recommended) — used when locating by slug
 *   - `lookup_field` (string, optional, default `id`) — mapped key holding the lookup value
 *
 * If `lookup_field` is `id`, the value is treated as a Statamic entry id;
 * otherwise we treat it as a slug within the configured `collection`.
 *
 * Reserved keys (`id`, `slug`, `site`, `published`) are removed from the
 * data payload before merging. `published` updates the publish state.
 */
class UpdateEntryHandler implements InboundActionHandlerInterface
{
    public function handle(): string
    {
        return 'update_entry';
    }

    public function label(): string
    {
        return 'Update entry';
    }

    public function handleAction(InboundEndpoint $endpoint, array $mappedPayload, array $rawPayload): array
    {
        $config = $endpoint->action_config ?? [];
        $lookupField = (string) ($config['lookup_field'] ?? 'id');
        $collection = (string) ($config['collection'] ?? '');
        $lookupValue = $mappedPayload[$lookupField] ?? null;

        if ($lookupValue === null || $lookupValue === '') {
            return [
                'ok' => false,
                'message' => "Missing lookup value '{$lookupField}' in mapped payload.",
                'data' => [],
            ];
        }

        try {
            $entry = $this->resolve($lookupField, (string) $lookupValue, $collection);
            if (! $entry) {
                return [
                    'ok' => false,
                    'message' => 'Entry not found.',
                    'data' => ['lookup_field' => $lookupField, 'lookup_value' => $lookupValue],
                ];
            }

            $data = $mappedPayload;
            unset($data['id'], $data['slug'], $data['site'], $data['published']);

            $entry->data(array_merge($entry->data()->all(), $data));

            if (array_key_exists('published', $mappedPayload)) {
                $entry->published((bool) $mappedPayload['published']);
            }
            if (! empty($mappedPayload['slug']) && is_string($mappedPayload['slug'])) {
                $entry->slug($mappedPayload['slug']);
            }

            $entry->save();

            return [
                'ok' => true,
                'message' => 'Entry updated.',
                'data' => [
                    'id' => $entry->id(),
                    'slug' => $entry->slug(),
                    'collection' => $entry->collectionHandle(),
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Failed to update entry: '.$e->getMessage(),
                'data' => [],
            ];
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
