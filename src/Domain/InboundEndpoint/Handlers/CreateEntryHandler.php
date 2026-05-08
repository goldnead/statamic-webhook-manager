<?php

namespace Goldnead\WebhookManager\Domain\InboundEndpoint\Handlers;

use Goldnead\WebhookManager\Contracts\InboundActionHandlerInterface;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Statamic\Facades\Entry;

/**
 * Creates a new entry from the mapped payload.
 *
 * Endpoint `action_config` must include:
 *   - `collection` (string, required) — handle of the target collection
 *   - `site` (string, optional) — site handle, defaults to default site
 *   - `published` (bool, optional, default true)
 *   - `slug_field` (string, optional, default `slug`) — mapped key to use as slug
 *
 * The mapped payload is written to entry data verbatim. `slug` and
 * `published` are pulled out as top-level fields, everything else is
 * stored as entry data.
 *
 * TODO: REVIEW — slug collision strategy (PRD §23 lists "Retry → Doppelanlage"
 * as an edge case). For v1 we leave duplicates to Statamic, which throws
 * on save; the dispatcher catches and reports `ok: false`.
 */
class CreateEntryHandler implements InboundActionHandlerInterface
{
    public function handle(): string
    {
        return 'create_entry';
    }

    public function label(): string
    {
        return 'Create entry';
    }

    public function handleAction(InboundEndpoint $endpoint, array $mappedPayload, array $rawPayload): array
    {
        $config = $endpoint->action_config ?? [];
        $collection = (string) ($config['collection'] ?? '');

        if ($collection === '') {
            return [
                'ok' => false,
                'message' => 'Missing required action_config.collection.',
                'data' => [],
            ];
        }

        try {
            $slugField = (string) ($config['slug_field'] ?? 'slug');
            $site = (string) ($config['site'] ?? ($mappedPayload['site'] ?? 'default'));
            $published = array_key_exists('published', $mappedPayload)
                ? (bool) $mappedPayload['published']
                : (bool) ($config['published'] ?? true);

            $data = $mappedPayload;
            $slug = $data[$slugField] ?? null;
            unset($data[$slugField], $data['published'], $data['site']);

            $entry = Entry::make()
                ->collection($collection)
                ->locale($site)
                ->published($published)
                ->data($data);

            if (is_string($slug) && $slug !== '') {
                $entry->slug($slug);
            }

            $entry->save();

            return [
                'ok' => true,
                'message' => 'Entry created.',
                'data' => [
                    'id' => $entry->id(),
                    'collection' => $collection,
                    'slug' => $entry->slug(),
                    'site' => $site,
                    'url' => $entry->url(),
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Failed to create entry: '.$e->getMessage(),
                'data' => [],
            ];
        }
    }
}
