<?php

namespace Goldnead\WebhookManager\Contracts;

use Goldnead\WebhookManager\ValueObjects\ExecutionContext;

interface PayloadRendererInterface
{
    /**
     * Render a payload string from a template + execution context.
     *
     * @return string rendered body, ready for transport.
     */
    public function render(string $template, ExecutionContext $context): string;

    /**
     * Validate template syntax and return a list of issues (empty = ok).
     *
     * @return array<int, string>
     */
    public function validate(string $template): array;
}
