<?php

namespace Goldnead\WebhookManager\Repositories;

use Goldnead\WebhookManager\Domain\Template\Models\Template;
use Illuminate\Support\Collection;

class TemplateRepository
{
    public function findByHandle(string $handle): ?Template
    {
        return Template::where('handle', $handle)->first();
    }

    public function ofType(string $type): Collection
    {
        return Template::where('type', $type)->orderBy('name')->get();
    }

    public function all(): Collection
    {
        return Template::orderBy('name')->get();
    }
}
