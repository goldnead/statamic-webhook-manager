<?php

namespace Goldnead\WebhookManager\Listeners;

use Goldnead\WebhookManager\Events\TriggerDetected;
use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Illuminate\Contracts\Events\Dispatcher;

class HandleFormSubmittedListener
{
    public function __construct(
        protected TriggerRegistry $triggers,
        protected Dispatcher $events,
    ) {
    }

    public function handle(object $event): void
    {
        $submission = $event->submission ?? null;
        if (! $submission) {
            return;
        }

        $trigger = $this->triggers->get('form.submitted');
        if (! $trigger) {
            return;
        }

        $this->events->dispatch(new TriggerDetected($trigger->build($submission)));
    }
}
