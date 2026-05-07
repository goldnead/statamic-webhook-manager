<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Repositories\DeliveryRepository;
use Goldnead\WebhookManager\Services\DeliveryMaskingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DeliveryController extends Controller
{
    public function index(Request $request, DeliveryRepository $repository)
    {
        abort_unless($request->user()?->can('view webhook deliveries'), 403);

        return view('webhook-manager::cp.deliveries.index', [
            'deliveries' => $repository->paginate(25, $request->only(['status', 'webhook_id', 'trigger', 'error_type'])),
            'filters' => $request->only(['status', 'webhook_id', 'trigger', 'error_type']),
        ]);
    }

    public function show(Request $request, Delivery $delivery, DeliveryMaskingService $masker)
    {
        abort_unless($request->user()?->can('view webhook deliveries'), 403);

        $canViewSensitive = $request->user()?->can('view sensitive payloads') === true;
        $masked = $masker->maskForViewer($delivery, $canViewSensitive);

        return view('webhook-manager::cp.deliveries.show', [
            'delivery' => $masked,
            'canReplay' => $request->user()?->can('replay webhook deliveries') === true,
            'canViewSensitive' => $canViewSensitive,
            'curl' => $this->buildCurl($masked, $canViewSensitive),
        ]);
    }

    /**
     * Build a "Copy as cURL" string. Sensitive headers are masked unless
     * the viewer has the appropriate permission.
     */
    protected function buildCurl(Delivery $delivery, bool $showSensitive): string
    {
        $parts = ["curl -X {$delivery->request_method}"];
        foreach ((array) $delivery->request_headers as $name => $value) {
            $value = is_array($value) ? implode(',', $value) : (string) $value;
            $parts[] = "  -H '".addslashes($name).': '.addslashes($value)."'";
        }
        $body = $delivery->request_body;
        if ($body) {
            $parts[] = "  -d '".addslashes($body)."'";
        }
        $parts[] = "  '".addslashes($delivery->request_url)."'";
        return implode(" \\\n", $parts);
    }
}
