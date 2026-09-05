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
        // `width` reaches the <td> (core's Listing/TableBody.vue:102). Without
        // it the auto-layout table gives the URL column whatever the four
        // badge columns leave over — measured at 108px, of which
        // MiddleEllipsis could use 80px, so every URL rendered as `ht...g` and
        // no two rows could be told apart. Percentages rather than pixels, so
        // the widths survive the header's max-width toggle.
        return [
            ['field' => 'name', 'label' => __('webhook-manager::messages.cp.col_name'), 'sortable' => true, 'visible' => true, 'width' => '24%'],
            ['field' => 'trigger_type', 'label' => __('webhook-manager::messages.cp.col_trigger'), 'sortable' => true, 'visible' => true, 'width' => '17%'],
            ['field' => 'method', 'label' => __('webhook-manager::messages.cp.col_method'), 'sortable' => false, 'visible' => true, 'width' => '8%'],
            ['field' => 'url', 'label' => __('webhook-manager::messages.cp.col_url'), 'sortable' => false, 'visible' => true, 'width' => '39%'],
            ['field' => 'enabled', 'label' => __('webhook-manager::messages.cp.col_status'), 'sortable' => true, 'visible' => true, 'width' => '12%'],
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
            'can_edit' => $canManage,
            'can_toggle' => $canManage,
            'can_test' => static::canTest($user),
            'can_delete' => $canManage,

            'edit_url' => cp_route('webhook-manager.outbound.edit', $hook),
            'toggle_url' => cp_route('webhook-manager.outbound.toggle', $hook),
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
