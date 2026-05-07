<?php

namespace Goldnead\WebhookManager\Listeners;

use Goldnead\WebhookManager\Events\TriggerDetected;
use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Illuminate\Contracts\Events\Dispatcher;

class HandleEntryDeletedListener
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

        $trigger = $this->triggers->get('entry.deleted');
        if (! $trigger) {
            return;
        }

        $this->events->dispatch(new TriggerDetected($trigger->build($entry)));
    }
}
