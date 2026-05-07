<?php

namespace Goldnead\WebhookManager\Templates\Actions;

use Goldnead\WebhookManager\Templates\TemplateRenderer;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;

/**
 * Renders a template against a sample payload for the CP debug screen.
 */
class PreviewTemplateAction
{
    public function __construct(protected TemplateRenderer $renderer)
    {
    }

    /**
     * @return array{rendered:string, issues:array<int,string>}
     */
    public function __invoke(string $template, array $samplePayload, string $sourceType = 'entry'): array
    {
        $event = new TriggerEvent(
            triggerHandle: 'preview',
            sourceType: $sourceType,
            sourceReference: $samplePayload['id'] ?? null,
            payload: $samplePayload,
            site: $samplePayload['site'] ?? 'default',
            locale: $samplePayload['locale'] ?? null,
        );
        $context = new ExecutionContext($event);

        $issues = $this->renderer->validate($template);
        try {
            $rendered = $this->renderer->render($template, $context);
        } catch (\Throwable $e) {
            $issues[] = $e->getMessage();
            $rendered = '';
        }

        return ['rendered' => $rendered, 'issues' => $issues];
    }
}
