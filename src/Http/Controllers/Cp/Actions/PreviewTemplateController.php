<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp\Actions;

use Goldnead\WebhookManager\Templates\Actions\PreviewTemplateAction;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PreviewTemplateController extends Controller
{
    public function __invoke(Request $request, PreviewTemplateAction $preview)
    {
        abort_unless($request->user()?->can('use webhook debug tools'), 403);

        $template = (string) $request->input('template', '');
        $payload = (array) $request->input('sample_payload', []);
        $sourceType = (string) $request->input('source_type', 'entry');

        return response()->json($preview($template, $payload, $sourceType));
    }
}
