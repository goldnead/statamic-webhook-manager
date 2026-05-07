<?php

namespace Goldnead\WebhookManager\Templates\Actions;

use Goldnead\WebhookManager\Templates\TemplateRenderer;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;

class RenderTemplateAction
{
    public function __construct(protected TemplateRenderer $renderer)
    {
    }

    public function __invoke(string $template, ExecutionContext $context): string
    {
        return $this->renderer->render($template, $context);
    }
}
