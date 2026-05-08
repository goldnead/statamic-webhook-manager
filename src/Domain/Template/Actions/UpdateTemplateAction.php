<?php

namespace Goldnead\WebhookManager\Domain\Template\Actions;

use Goldnead\WebhookManager\Domain\Template\Models\Template;

class UpdateTemplateAction
{
    public function __invoke(Template $template, array $attributes): Template
    {
        $template->fill($attributes);
        $template->save();

        return $template->fresh();
    }
}
