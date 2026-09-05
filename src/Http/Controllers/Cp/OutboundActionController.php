<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Actions\Cp\DeleteOutboundWebhook;
use Goldnead\WebhookManager\Actions\Cp\DisableOutboundWebhook;
use Goldnead\WebhookManager\Actions\Cp\EnableOutboundWebhook;
use Goldnead\WebhookManager\Contracts\Repositories\OutboundWebhookRepositoryInterface;
use Goldnead\WebhookManager\Http\Controllers\Cp\Concerns\ScopesActionsToItsEndpoint;
use Statamic\Http\Controllers\CP\ActionController;

/**
 * The two endpoints core's <Listing> talks to for row and bulk actions:
 * `POST …/outbound/actions/list` asks which actions apply to a selection,
 * `POST …/outbound/actions` runs one. Both are core's; all this class supplies
 * is the lookup from a checked id back to a model.
 *
 * Until this controller existed, `actionUrl` on the outbound listing pointed at
 * the index route, so the checkbox column was there and the bulk menu was
 * empty — checkboxes without an action behind them.
 *
 * The lookup goes through the repository rather than Eloquent so it works under
 * both storage drivers, and because the repository carries the brand scope:
 * this is a route that takes ids straight from the browser, and the scope is
 * what keeps an operator in brand A from acting on a brand B row.
 */
class OutboundActionController extends ActionController
{
    use ScopesActionsToItsEndpoint;

    protected static $key = 'webhook-manager.outbound';

    /** @return array<int, string> */
    protected function allowedActions(): array
    {
        return [
            EnableOutboundWebhook::handle(),
            DisableOutboundWebhook::handle(),
            DeleteOutboundWebhook::handle(),
        ];
    }

    protected function getSelectedItems($items, $context)
    {
        $repository = app(OutboundWebhookRepositoryInterface::class);

        return $items
            ->map(fn ($id) => $repository->find($id))
            ->filter()
            ->values();
    }
}
