<?php

namespace Goldnead\WebhookManager\Listeners;

use Goldnead\WebhookManager\Events\TriggerDetected;
use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Illuminate\Contracts\Events\Dispatcher;

class HandleUserSavedListener
{
    public function __construct(
        protected TriggerRegistry $triggers,
        protected Dispatcher $events,
    ) {
    }

    public function handle(object $event): void
    {
        $user = $event->user ?? null;
        if (! $user) {
            return;
        }

        $trigger = $this->triggers->get('user.saved');
        if (! $trigger) {
            return;
        }

        $this->events->dispatch(new TriggerDetected($trigger->build($user)));
    }
}
