<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Domain\Template\Models\Template;
use Goldnead\WebhookManager\Repositories\TemplateRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Http\Controllers\CP\CpController;

class TemplateController extends CpController
{
    public function index(Request $request, TemplateRepository $repository)
    {
        abort_unless($request->user()?->can('manage webhook templates'), 403);

        $templates = $repository->all()->map(fn (Template $tpl) => [
            'id' => $tpl->id,
            'uuid' => $tpl->uuid,
            'name' => $tpl->name,
            'handle' => $tpl->handle,
            'type' => $tpl->type,
        ])->values();

        return Inertia::render('webhook-manager::Templates/Index', [
            'templates' => $templates,
        ]);
    }
}
