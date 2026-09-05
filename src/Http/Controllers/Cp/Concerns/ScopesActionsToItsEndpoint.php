<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp\Concerns;

use Illuminate\Http\Request;
use Statamic\Facades\Action;

/**
 * Two guards core's `ActionController` does not have, in front of both of its
 * endpoints. Both were found by review on 05.09.2026 with a plain POST.
 *
 * ## 1. An empty selection must not reach core
 *
 * `getSelectedItems()` looks ids up and drops what it cannot resolve — a
 * deleted row, a stale page, another brand's id. When *everything* is dropped,
 * core is handed an EMPTY collection, and that is where it goes wrong:
 * `Action::visibleToBulk()` and `authorizeBulk()` decide by comparing counts
 * (`vendor/statamic/cms/src/Actions/Action.php:42-49,56-63`), and `0 === 0` is
 * true for **every** registered action of **every** installed addon. So
 * `ActionRepository::forBulk()` walks the global action list and dies inside a
 * foreign action's `fieldItems()` (`Statamic\Actions\MoveAsset`: "Undefined
 * array key container").
 *
 * Two consequences, both bad: an HTTP 500 with a stack trace full of absolute
 * server paths — and, without that crash, the complete action list of every
 * installed addon would have been handed to the caller.
 *
 * ## 2. An action from a foreign endpoint must not run here
 *
 * `ActionController::run()` resolves the handle straight out of the global
 * registry and checks only `authorize()`; `visibleTo()` is a UI filter and is
 * never consulted (`ActionController.php:26-36`). Posting
 * `webhook_manager_replay_delivery` to the *outbound* endpoint therefore ran a
 * delivery replay over an OutboundWebhook and reported success.
 *
 * Each controller declares which handles it serves; anything else is a 404 —
 * that action does not exist at this endpoint.
 *
 * The per-action `authorize()` type checks are the second lock on the same
 * door. This one is the cheaper and more explicit of the two, and it also
 * keeps a *legitimate* foreign action (an entry action, say) from being run
 * against webhook rows.
 */
trait ScopesActionsToItsEndpoint
{
    /**
     * Handles of the actions this endpoint serves.
     *
     * @return array<int, string>
     */
    abstract protected function allowedActions(): array;

    public function run(Request $request)
    {
        $this->abortUnlessActionBelongsHere((string) $request->input('action'));
        $this->abortUnlessSelectionResolves($request);

        return parent::run($request);
    }

    public function bulkActions(Request $request)
    {
        $data = $request->validate([
            'selections' => 'required|array',
            'context' => 'sometimes',
        ]);

        $context = $data['context'] ?? [];
        $items = $this->getSelectedItems(collect($data['selections']), $context);

        // The whole point of this override: never ask core about nothing.
        if ($items->isEmpty()) {
            return [];
        }

        return Action::forBulk($items, $context);
    }

    private function abortUnlessActionBelongsHere(string $handle): void
    {
        abort_unless(
            in_array($handle, $this->allowedActions(), true),
            404,
            __('webhook-manager::messages.cp.action_not_available_here')
        );
    }

    private function abortUnlessSelectionResolves(Request $request): void
    {
        $selections = $request->input('selections');

        if (! is_array($selections)) {
            // Let core's own validator produce the 422 for a missing key.
            return;
        }

        $items = $this->getSelectedItems(collect($selections), $request->input('context') ?? []);

        abort_if(
            $items->isEmpty(),
            404,
            __('webhook-manager::messages.cp.action_selection_gone')
        );
    }
}
