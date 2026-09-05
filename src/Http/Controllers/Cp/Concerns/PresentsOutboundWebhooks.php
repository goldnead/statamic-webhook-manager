<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp\Concerns;

use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Illuminate\Http\Request;

/**
 * One shape for an outbound webhook row, used by the outbound listing and by
 * the overview screen.
 *
 * The overview shows the same webhooks as `/outbound`, so it must show them the
 * same way: same columns, same badges, same row actions, same permission flags.
 * Duplicating the mapping is how the two screens drift apart — one gains a
 * column and the other quietly does not.
 */
trait PresentsOutboundWebhooks
{
    /**
     * Columns for a listing of outbound webhooks.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function outboundColumns(): array
    {
        // These carried `width` percentages until 05.09.2026, on the belief
        // that reaching the <td> made them work. Half of that is true: core's
        // ListingTableBody does put the value on the <td> (the <th> gets
        // nothing, and there is no <colgroup>). It still does nothing, because
        // `.data-table` is pinned to `width:100%; min-width:100%` at
        // `table-layout: auto` — that scales a PREFERRED width away and a
        // MINIMUM one not. Measured: `td[width]`, an inline `!important` width
        // on the <td> and one on the <th> all left the column where it was.
        // What actually widened this listing's URL column from 119px to 284px
        // was the `min-w-64` on the cell in Outbound/Index.vue. Keeping a dead
        // key here would keep the wrong explanation alive with it.
        return [
            ['field' => 'name', 'label' => __('webhook-manager::messages.cp.col_name'), 'sortable' => true, 'visible' => true],
            ['field' => 'trigger_type', 'label' => __('webhook-manager::messages.cp.col_trigger'), 'sortable' => true, 'visible' => true],
            ['field' => 'method', 'label' => __('webhook-manager::messages.cp.col_method'), 'sortable' => false, 'visible' => true],
            ['field' => 'url', 'label' => __('webhook-manager::messages.cp.col_url'), 'sortable' => false, 'visible' => true],
            ['field' => 'enabled', 'label' => __('webhook-manager::messages.cp.col_status'), 'sortable' => true, 'visible' => true],
        ];
    }

    /**
     * Single-row payload for the listing. Includes pre-computed permission
     * flags and helper URLs so the Vue page never has to check abilities or
     * build routes itself.
     *
     * @param  array<string,string>  $triggerLabels
     * @return array<string,mixed>
     */
    protected function outboundRow(OutboundWebhook $hook, Request $request, array $triggerLabels): array
    {
        $user = $request->user();
        $canManage = (bool) $user?->can('manage outbound webhooks');

        return [
            'id' => $hook->id,
            'uuid' => $hook->uuid,
            'name' => $hook->name,
            'handle' => $hook->handle,
            'trigger_type' => $hook->trigger_type,
            'trigger_label' => $triggerLabels[$hook->trigger_type] ?? $hook->trigger_type,
            'url' => $hook->url,
            'method' => $hook->method,
            'enabled' => (bool) $hook->enabled,

            // Permissions surfaced to the UI (so v-if conditions stay
            // declarative and don't leak ability strings into Vue).
            // `can_toggle` / `toggle_url` used to ride along here. Nothing
            // reads them any more: enabling and disabling is a native Statamic
            // action since 05.09.2026, and the PATCH route they pointed at is
            // only used by the edit screen, which builds its own URL.
            'can_edit' => $canManage,
            'can_test' => static::canTest($user),
            'can_delete' => $canManage,

            'edit_url' => cp_route('webhook-manager.outbound.edit', $hook),
            'delete_url' => cp_route('webhook-manager.outbound.destroy', $hook),
            'test_url' => cp_route('webhook-manager.actions.test-outbound', $hook),
        ];
    }

    /**
     * May this user fire a test request? Public and static because both the
     * listing and the overview ask, and neither owns the rule.
     */
    public static function canTest(mixed $user): bool
    {
        if ($user === null) {
            return false;
        }

        return (bool) $user->can('test outbound webhooks')
            || (bool) $user->can('manage outbound webhooks');
    }
}
