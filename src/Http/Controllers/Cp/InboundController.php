<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Repositories\InboundEndpointRepository;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * TODO: REVIEW — list endpoints work, edit screens are next iteration.
 */
class InboundController extends Controller
{
    public function index(Request $request, InboundEndpointRepository $repository)
    {
        abort_unless($request->user()?->can('manage inbound endpoints'), 403);

        return view('webhook-manager::cp.inbound.index', [
            'endpoints' => $repository->all(),
        ]);
    }
}
