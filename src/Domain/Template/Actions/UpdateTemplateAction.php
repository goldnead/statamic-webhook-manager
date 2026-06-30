<?php

namespace Goldnead\WebhookManager\Domain\Template\Actions;

use Goldnead\WebhookManager\Contracts\Repositories\TemplateRepositoryInterface;
use Goldnead\WebhookManager\Domain\Template\Models\Template;

class UpdateTemplateAction
{
    public function __construct(protected TemplateRepositoryInterface $repository)
    {
    }

    public function __invoke(Template $template, array $attributes): Template
    {
        $template->fill($attributes);

        return $this->repository->save($template);
    }
}
