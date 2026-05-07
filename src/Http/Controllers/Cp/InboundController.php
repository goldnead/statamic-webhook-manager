<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Goldnead\WebhookManager\Repositories\InboundEndpointRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Http\Controllers\CP\CpController;

/**
 * TODO: REVIEW — listing only; full CRUD ships with the inbound iteration.
 */
class InboundController extends CpController
{
    public function index(Request $request, InboundEndpointRepository $repository)
    {
        abort_unless($request->user()?->can('manage inbound endpoints'), 403);

        $endpoints = $repository->all()->map(fn (InboundEndpoint $e) => [
            'id' => $e->id,
            'uuid' => $e->uuid,
            'name' => $e->name,
            'handle' => $e->handle,
            'path' => $e->path,
            'auth_type' => $e->auth_type,
            'enabled' => (bool) $e->enabled,
        ])->values();

        return Inertia::render('webhook-manager::Inbound/Index', [
            'endpoints' => $endpoints,
        ]);
    }
}
