<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Domain\OutboundWebhook\Actions\CreateOutboundWebhookAction;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Actions\DeleteOutboundWebhookAction;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Actions\ToggleOutboundWebhookAction;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Actions\UpdateOutboundWebhookAction;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Http\Requests\SaveOutboundWebhookRequest;
use Goldnead\WebhookManager\Registries\AuthSchemeRegistry;
use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Goldnead\WebhookManager\Repositories\OutboundWebhookRepository;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OutboundController extends Controller
{
    public function index(Request $request, OutboundWebhookRepository $repository)
    {
        $this->authorizeAny($request, 'manage outbound webhooks', 'view webhooks');

        return view('webhook-manager::cp.outbound.index', [
            'webhooks' => $repository->paginate(25, $request->get('q')),
        ]);
    }

    public function create(Request $request, TriggerRegistry $triggers, AuthSchemeRegistry $auth)
    {
        $this->authorizeOr403($request, 'manage outbound webhooks');

        return view('webhook-manager::cp.outbound.edit', [
            'webhook' => new OutboundWebhook([
                'method' => 'POST',
                'enabled' => true,
                'queue_enabled' => true,
                'auth_type' => 'none',
                'payload_type' => 'raw_json',
                'timeout_seconds' => 15,
                'follow_redirects' => true,
                'log_body_mode' => 'partial',
            ]),
            'triggerOptions' => $triggers->options(),
            'authOptions' => $auth->options(),
            'isNew' => true,
        ]);
    }

    public function store(
        SaveOutboundWebhookRequest $request,
        CreateOutboundWebhookAction $create,
    ) {
        $this->authorizeOr403($request, 'manage outbound webhooks');

        $hook = ($create)($request->validated());

        return redirect()
            ->route('webhook-manager.outbound.edit', $hook)
            ->with('success', __('webhook-manager::messages.created'));
    }

    public function edit(Request $request, OutboundWebhook $webhook, TriggerRegistry $triggers, AuthSchemeRegistry $auth)
    {
        $this->authorizeOr403($request, 'manage outbound webhooks');

        return view('webhook-manager::cp.outbound.edit', [
            'webhook' => $webhook,
            'triggerOptions' => $triggers->options(),
            'authOptions' => $auth->options(),
            'isNew' => false,
        ]);
    }

    public function update(
        SaveOutboundWebhookRequest $request,
        OutboundWebhook $webhook,
        UpdateOutboundWebhookAction $update,
    ) {
        $this->authorizeOr403($request, 'manage outbound webhooks');

        ($update)($webhook, $request->validated());

        return back()->with('success', __('webhook-manager::messages.updated'));
    }

    public function destroy(Request $request, OutboundWebhook $webhook, DeleteOutboundWebhookAction $delete)
    {
        $this->authorizeOr403($request, 'manage outbound webhooks');

        ($delete)($webhook);

        return redirect()
            ->route('webhook-manager.outbound.index')
            ->with('success', __('webhook-manager::messages.deleted'));
    }

    public function toggle(Request $request, OutboundWebhook $webhook, ToggleOutboundWebhookAction $toggle)
    {
        $this->authorizeOr403($request, 'manage outbound webhooks');

        $webhook = ($toggle)($webhook);

        return back()->with(
            'success',
            $webhook->enabled
                ? __('webhook-manager::messages.enabled')
                : __('webhook-manager::messages.disabled'),
        );
    }

    private function authorizeOr403(Request $request, string $ability): void
    {
        abort_unless($request->user()?->can($ability), 403);
    }

    private function authorizeAny(Request $request, string ...$abilities): void
    {
        $user = $request->user();
        foreach ($abilities as $ability) {
            if ($user?->can($ability)) {
                return;
            }
        }
        abort(403);
    }
}
