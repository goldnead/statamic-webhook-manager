<?php

namespace Goldnead\WebhookManager\Templates;

class TemplateValidator
{
    public function __construct(protected TemplateRenderer $renderer)
    {
    }

    /**
     * @return array{valid: bool, issues: array<int, string>}
     */
    public function check(string $template, bool $expectJson = false): array
    {
        $issues = $this->renderer->validate($template);

        if ($expectJson) {
            // Strip tokens and confirm the surrounding shell is valid JSON.
            $stripped = preg_replace(TemplateRenderer::TOKEN_PATTERN, '"_token_"', $template) ?? $template;
            json_decode($stripped, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $issues[] = 'Template is not valid JSON: '.json_last_error_msg();
            }
        }

        return ['valid' => $issues === [], 'issues' => $issues];
    }
}
