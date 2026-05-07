<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->can('manage webhook settings'), 403);

        return view('webhook-manager::cp.settings.index', [
            'config' => config('webhook-manager'),
        ]);
    }
}
