<?php

namespace Goldnead\WebhookManager\Actions;

use Goldnead\WebhookManager\Contracts\ActionInterface;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\ExecutionResult;
use Statamic\Facades\Entry;

/**
 * Create a Statamic entry from a rule's execution context.
 *
 * Rule config:
 *   - `collection` (string, required)
 *   - `site` (string, optional, default: trigger event site or 'default')
 *   - `published` (bool, optional, default true)
 *   - `slug_field` (string, optional, default `slug`)
 *   - `data` (object/array, optional) — explicit data values; if absent the
 *     entire trigger payload is used.
 *
 * Reserved keys (`slug`, `published`, `site`) are pulled out of the data map.
 */
class CreateEntryAction implements ActionInterface
{
    public function handle(): string
    {
        return 'create_entry';
    }

    public function label(): string
    {
        return 'Create entry';
    }

    public function execute(array $config, ExecutionContext $context): ExecutionResult
    {
        $collection = (string) ($config['collection'] ?? '');
        if ($collection === '') {
            return ExecutionResult::fail('Missing required config.collection.');
        }

        try {
            $data = is_array($config['data'] ?? null) ? $config['data'] : $context->payload();
            $slugField = (string) ($config['slug_field'] ?? 'slug');
            $site = (string) ($config['site'] ?? $context->event->site ?? 'default');
            $published = array_key_exists('published', $config)
                ? (bool) $config['published']
                : (array_key_exists('published', $data) ? (bool) $data['published'] : true);

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

            return ExecutionResult::ok('Entry created.', [
                'id' => $entry->id(),
                'collection' => $collection,
                'slug' => $entry->slug(),
                'site' => $site,
            ]);
        } catch (\Throwable $e) {
            return ExecutionResult::fail('Failed to create entry: '.$e->getMessage());
        }
    }
}
