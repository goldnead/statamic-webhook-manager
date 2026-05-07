<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Repositories\LogRepository;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LogController extends Controller
{
    public function index(Request $request, LogRepository $repository)
    {
        abort_unless($request->user()?->can('view webhooks'), 403);

        return view('webhook-manager::cp.logs.index', [
            'logs' => $repository->paginate(25, $request->only(['level', 'type', 'correlation_id'])),
            'filters' => $request->only(['level', 'type', 'correlation_id']),
        ]);
    }
}
