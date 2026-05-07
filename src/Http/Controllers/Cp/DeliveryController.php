<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Repositories\DeliveryRepository;
use Goldnead\WebhookManager\Services\DeliveryMaskingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Http\Controllers\CP\CpController;

class DeliveryController extends CpController
{
    public function index(Request $request, DeliveryRepository $repository)
    {
        abort_unless($request->user()?->can('view webhook deliveries'), 403);

        $filters = $request->only(['status', 'webhook_id', 'trigger', 'error_type']);
        $deliveries = $repository->paginate(25, $filters);
        $rows = $deliveries->getCollection()->map(fn (Delivery $d) => $this->row($d));

        $payload = [
            'data' => $rows,
            'meta' => [
                'current_page' => $deliveries->currentPage(),
                'last_page' => $deliveries->lastPage(),
                'per_page' => $deliveries->perPage(),
                'total' => $deliveries->total(),
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('webhook-manager::Deliveries/Index', [
            'deliveries' => $payload,
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, Delivery $delivery, DeliveryMaskingService $masker)
    {
        abort_unless($request->user()?->can('view webhook deliveries'), 403);

        $canViewSensitive = $request->user()?->can('view sensitive payloads') === true;
        $masked = $masker->maskForViewer($delivery, $canViewSensitive);

        return Inertia::render('webhook-manager::Deliveries/Show', [
            'delivery' => [
                'id' => $masked->id,
                'uuid' => $masked->uuid,
                'status' => $masked->status,
                'status_badge' => $masked->statusBadge(),
                'trigger_type' => $masked->trigger_type,
                'trigger_reference' => $masked->trigger_reference,
                'correlation_id' => $masked->correlation_id,
                'attempts' => (int) $masked->attempts,
                'duration_ms' => $masked->duration_ms,
                'first_attempted_at' => $masked->first_attempted_at?->toIso8601String(),
                'last_attempted_at' => $masked->last_attempted_at?->toIso8601String(),
                'next_retry_at' => $masked->next_retry_at?->toIso8601String(),
                'first_attempted_human' => $masked->first_attempted_at?->diffForHumans(),
                'last_attempted_human' => $masked->last_attempted_at?->diffForHumans(),
                'next_retry_human' => $masked->next_retry_at?->diffForHumans(),
                'error_type' => $masked->error_type,
                'error_message' => $masked->error_message,
                'request_url' => $masked->request_url,
                'request_method' => $masked->request_method,
                'request_headers' => $masked->request_headers,
                'request_body' => $masked->request_body,
                'response_status' => $masked->response_status,
                'response_headers' => $masked->response_headers,
                'response_body' => $masked->response_body,
                'curl' => $this->buildCurl($masked),
            ],
            'canReplay' => $request->user()?->can('replay webhook deliveries') === true
                && $masked->isReplayable(),
            'canViewSensitive' => $canViewSensitive,
            'replayUrl' => cp_route('webhook-manager.actions.replay-delivery', $masked),
            'indexUrl' => cp_route('webhook-manager.deliveries.index'),
        ]);
    }

    /** @return array<string,mixed> */
    protected function row(Delivery $delivery): array
    {
        return [
            'id' => $delivery->id,
            'uuid' => $delivery->uuid,
            'status' => $delivery->status,
            'status_badge' => $delivery->statusBadge(),
            'trigger_type' => $delivery->trigger_type,
            'request_url' => $delivery->request_url,
            'response_status' => $delivery->response_status,
            'attempts' => (int) $delivery->attempts,
            'created_at_human' => $delivery->created_at?->diffForHumans(),
            'show_url' => cp_route('webhook-manager.deliveries.show', $delivery),
        ];
    }

    protected function buildCurl(Delivery $delivery): string
    {
        $parts = ["curl -X {$delivery->request_method}"];
        foreach ((array) $delivery->request_headers as $name => $value) {
            $value = is_array($value) ? implode(',', $value) : (string) $value;
            $parts[] = "  -H '".addslashes($name).': '.addslashes($value)."'";
        }
        if ($body = $delivery->request_body) {
            $parts[] = "  -d '".addslashes($body)."'";
        }
        $parts[] = "  '".addslashes($delivery->request_url)."'";
        return implode(" \\\n", $parts);
    }
}
