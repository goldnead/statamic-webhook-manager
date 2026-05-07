<?php

namespace Goldnead\WebhookManager\Templates\Actions;

use Goldnead\WebhookManager\Templates\TemplateValidator;

class ValidateTemplateAction
{
    public function __construct(protected TemplateValidator $validator)
    {
    }

    /**
     * @return array{valid:bool, issues:array<int,string>}
     */
    public function __invoke(string $template, bool $expectJson = false): array
    {
        return $this->validator->check($template, $expectJson);
    }
}
