<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Http\Controllers\CP\CpController;

class SettingsController extends CpController
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->can('manage webhook settings'), 403);

        return Inertia::render('webhook-manager::Settings/Index', [
            'config' => config('webhook-manager'),
            'configPath' => 'config/webhook-manager.php',
            'publishCommand' => 'php please vendor:publish --tag=webhook-manager-config',
        ]);
    }
}
