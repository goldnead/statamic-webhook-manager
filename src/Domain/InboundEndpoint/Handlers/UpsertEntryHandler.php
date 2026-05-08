<?php

namespace Goldnead\WebhookManager\Domain\InboundEndpoint\Handlers;

use Goldnead\WebhookManager\Contracts\InboundActionHandlerInterface;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Statamic\Facades\Entry;

/**
 * Upserts an entry by a deterministic key.
 *
 * Endpoint `action_config` must include:
 *   - `collection` (string, required)
 *   - `lookup_field` (string, required) — mapped key whose value identifies the entry
 *   - `site` (string, optional)
 *
 * Behaviour:
 *   - if an entry exists where `{lookup_field}` matches the mapped payload's value,
 *     it's updated;
 *   - otherwise a new entry is created.
 *
 * The `lookup_field` value is also written to entry data so the next
 * call resolves the same entry. Reserved keys (`id`, `published`, `site`)
 * are removed from the data merge.
 */
class UpsertEntryHandler implements InboundActionHandlerInterface
{
    public function handle(): string
    {
        return 'upsert_entry';
    }

    public function label(): string
    {
        return 'Upsert entry by key';
    }

    public function handleAction(InboundEndpoint $endpoint, array $mappedPayload, array $rawPayload): array
    {
        $config = $endpoint->action_config ?? [];
        $collection = (string) ($config['collection'] ?? '');
        $lookupField = (string) ($config['lookup_field'] ?? '');

        if ($collection === '' || $lookupField === '') {
            return [
                'ok' => false,
                'message' => 'action_config.collection and action_config.lookup_field are required.',
                'data' => [],
            ];
        }

        $lookupValue = $mappedPayload[$lookupField] ?? null;
        if ($lookupValue === null || $lookupValue === '') {
            return [
                'ok' => false,
                'message' => "Mapped payload is missing the lookup value '{$lookupField}'.",
                'data' => [],
            ];
        }

        try {
            $site = (string) ($config['site'] ?? ($mappedPayload['site'] ?? 'default'));
            $existing = Entry::query()
                ->where('collection', $collection)
                ->where($lookupField, $lookupValue)
                ->first();

            $data = $mappedPayload;
            unset($data['id'], $data['site'], $data['published']);

            if ($existing) {
                $existing->data(array_merge($existing->data()->all(), $data));
                if (array_key_exists('published', $mappedPayload)) {
                    $existing->published((bool) $mappedPayload['published']);
                }
                $existing->save();

                return [
                    'ok' => true,
                    'message' => 'Entry updated (upsert).',
                    'data' => [
                        'id' => $existing->id(),
                        'created' => false,
                        'collection' => $collection,
                    ],
                ];
            }

            $published = array_key_exists('published', $mappedPayload)
                ? (bool) $mappedPayload['published']
                : (bool) ($config['published'] ?? true);

            $entry = Entry::make()
                ->collection($collection)
                ->locale($site)
                ->published($published)
                ->data($data);

            if (! empty($mappedPayload['slug']) && is_string($mappedPayload['slug'])) {
                $entry->slug($mappedPayload['slug']);
            }
            $entry->save();

            return [
                'ok' => true,
                'message' => 'Entry created (upsert).',
                'data' => [
                    'id' => $entry->id(),
                    'created' => true,
                    'collection' => $collection,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Failed to upsert entry: '.$e->getMessage(),
                'data' => [],
            ];
        }
    }
}
