<?php

namespace Goldnead\WebhookManager\Listeners;

use Goldnead\WebhookManager\Events\TriggerDetected;
use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Statamic 5 emits EntrySaved both for normal saves and publishes; this
 * listener inspects the entry's published flag to decide whether to fire
 * the dedicated entry.published trigger.
 *
 * TODO: REVIEW — replace with a proper `EntryPublished` event listener
 * once Statamic ships one (or when this addon adds an internal hook).
 */
class HandleEntryPublishedListener
{
    public function __construct(
        protected TriggerRegistry $triggers,
        protected Dispatcher $events,
    ) {
    }

    public function handle(object $event): void
    {
        $entry = $event->entry ?? null;
        if (! $entry) {
            return;
        }

        $isPublished = false;
        if (is_object($entry) && method_exists($entry, 'published')) {
            try {
                $isPublished = (bool) $entry->published();
            } catch (\Throwable $e) {
                $isPublished = false;
            }
        }

        if (! $isPublished) {
            return;
        }

        $trigger = $this->triggers->get('entry.published');
        if (! $trigger) {
            return;
        }

        $this->events->dispatch(new TriggerDetected($trigger->build($entry)));
    }
}
