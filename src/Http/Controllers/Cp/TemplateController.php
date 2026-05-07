<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Repositories\TemplateRepository;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TemplateController extends Controller
{
    public function index(Request $request, TemplateRepository $repository)
    {
        abort_unless($request->user()?->can('manage webhook templates'), 403);

        return view('webhook-manager::cp.templates.index', [
            'templates' => $repository->all(),
        ]);
    }
}
