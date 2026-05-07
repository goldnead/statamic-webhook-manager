<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp\Actions;

use Goldnead\WebhookManager\Events\TriggerDetected;
use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Fires a synthetic TriggerDetected event so the user can verify wiring.
 */
class SimulateTriggerController extends Controller
{
    public function __invoke(
        Request $request,
        TriggerRegistry $triggers,
        Dispatcher $events,
    ) {
        abort_unless($request->user()?->can('use webhook debug tools'), 403);

        $handle = (string) $request->input('trigger', 'entry.published');
        $payload = (array) $request->input('payload', []);

        $trigger = $triggers->get($handle);
        if (! $trigger) {
            return response()->json(['ok' => false, 'error' => "Unknown trigger: {$handle}"], 422);
        }

        $event = new TriggerEvent(
            triggerHandle: $handle,
            sourceType: $trigger->sourceType(),
            sourceReference: $payload['id'] ?? 'simulated',
            payload: $payload,
            isReplay: false,
            eventAt: new \DateTimeImmutable(),
        );

        $events->dispatch(new TriggerDetected($event));

        return response()->json(['ok' => true, 'correlation_id' => $event->correlationId]);
    }
}
