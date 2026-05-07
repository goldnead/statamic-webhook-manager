<?php

namespace Goldnead\WebhookManager\Templates\Context;

use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;

class TemplateContextFactory
{
    public function fromTrigger(TriggerEvent $trigger, array $extra = []): ExecutionContext
    {
        return new ExecutionContext($trigger, $extra);
    }
}
