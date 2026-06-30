<?php

namespace Goldnead\WebhookManager\Domain\Template\Actions;

use Goldnead\WebhookManager\Contracts\Repositories\TemplateRepositoryInterface;
use Goldnead\WebhookManager\Domain\Template\Models\Template;
use Illuminate\Support\Str;

class CreateTemplateAction
{
    public function __construct(protected TemplateRepositoryInterface $repository)
    {
    }

    public function __invoke(array $attributes): Template
    {
        return $this->repository->create($this->normalize($attributes));
    }

    protected function normalize(array $attributes): array
    {
        $attributes['handle'] = $attributes['handle']
            ?? Str::slug($attributes['name'] ?? Str::random(8));
        $attributes['type'] = $attributes['type'] ?? Template::TYPE_OUTBOUND_BODY;
        $attributes['body'] = (string) ($attributes['body'] ?? '');

        return $attributes;
    }
}
