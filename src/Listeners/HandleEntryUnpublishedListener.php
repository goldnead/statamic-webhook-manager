<?php

namespace Goldnead\WebhookManager\Listeners;

use Goldnead\WebhookManager\Events\TriggerDetected;
use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Illuminate\Contracts\Events\Dispatcher;

class HandleEntryUnpublishedListener
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

        if ($isPublished) {
            return;
        }

        $trigger = $this->triggers->get('entry.unpublished');
        if (! $trigger) {
            return;
        }

        $this->events->dispatch(new TriggerDetected($trigger->build($entry)));
    }
}
