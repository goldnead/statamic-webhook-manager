<script setup>
import axios from 'axios';
import { ref, computed } from 'vue';
import { Head, Link, router } from '@statamic/cms/inertia';
import {
    Header,
    Button,
    Panel,
    Card,
    Table,
    TableColumns,
    TableColumn,
    TableRows,
    TableRow,
    TableCell,
    Badge,
    Icon,
    Alert,
    Listing,
    DropdownItem,
    EmptyStateMenu,
    EmptyStateItem,
    DocsCallout,
    MiddleEllipsis,
    CommandPaletteItem,
} from '@statamic/cms/ui';

/**
 * Overview screen.
 *
 * Two things were wrong with the previous version, and they were the same
 * thing twice: the screen did not look like Statamic, and it did not show the
 * objects it is about.
 *
 * 1. The four figures sat in a grid of cards. Statamic has no card grid for
 *    numbers on a sub-page — cards are dashboard widgets, everything else in
 *    the CP is a table. Adrian decided this on 03.09.2026 for the sibling
 *    Insights screen (approval-insights-kennzahlen-darstellung, Weg A); the
 *    same reasoning applies here, so the figures are now four rows of a
 *    two-column table, each linking to the screen behind the number.
 * 2. The screen listed failures but not a single webhook. The outbound
 *    webhooks now sit in the middle of the page as a real listing, with the
 *    same columns, badges and row actions as `/outbound` (the row shape comes
 *    from one shared trait, so the two cannot drift).
 *
 * Both listings run client-side (`:items`) — they are summaries of screens
 * that already exist with their own routes, so there is nothing to paginate.
 * Both carry a real `actionUrl`, so the checkbox column and the "…" menu
 * actually do something (Enable/Disable/Delete, Replay).
 */
const props = defineProps({
    stats: { type: Array, required: true },

    webhooks: { type: Array, default: () => [] },
    webhookColumns: { type: Array, required: true },
    outboundIndexUrl: { type: String, required: true },
    outboundActionUrl: { type: String, required: true },

    recentFailures: { type: Array, default: () => [] },
    failureColumns: { type: Array, required: true },
    deliveriesIndexUrl: { type: String, required: true },
    deliveryActionUrl: { type: String, required: true },

    isEmpty: { type: Boolean, default: false },

    // Create URLs
    createOutboundUrl: { type: String, default: null },
    createInboundUrl: { type: String, default: null },
    createRuleUrl: { type: String, default: null },

    // Permission flags
    canCreateOutbound: { type: Boolean, default: false },
    canCreateInbound: { type: Boolean, default: false },
    canCreateRule: { type: Boolean, default: false },
});

const hasRecentFailures = computed(() => props.recentFailures?.length > 0);
const hasWebhooks = computed(() => props.webhooks?.length > 0);

/**
 * Client-side listings have nothing to re-fetch on their own, so after an
 * action ran the rows would still show the old state. Reloading the Inertia
 * props is what puts the new state on screen.
 */
const reloadPage = () => router.reload({ only: ['webhooks', 'recentFailures', 'stats'] });

// Statamic's Badge expects semantic colours. Same mapping as the outbound
// listing so a POST badge is the same green on both screens.
const methodColor = (method) => {
    switch ((method || '').toUpperCase()) {
        case 'GET': return 'blue';
        case 'POST': return 'green';
        case 'PUT': return 'amber';
        case 'PATCH': return 'amber';
        case 'DELETE': return 'red';
        default: return 'default';
    }
};

// ── Test ────────────────────────────────────────────────────────────────────
// `test_url` is a POST-only route; a DropdownItem with :href would issue a GET
// and land on a 404 without sending anything (the bug this addon already had
// once on the outbound listing).

const testResult = ref(null);
const testingId = ref(null);

async function runTest(hook) {
    if (!hook.test_url || testingId.value) return;
    testingId.value = hook.id;
    testResult.value = null;
    try {
        const res = await axios.post(hook.test_url, { sample_payload: {} });
        testResult.value = { ...res.data, name: hook.name };
    } catch (e) {
        testResult.value = {
            ok: false,
            name: hook.name,
            error_message: e?.response?.data?.message ?? e.message,
        };
    } finally {
        testingId.value = null;
    }
}
</script>

<template>
    <Head :title="[__('webhook-manager::messages.cp.overview'), __('Webhook Manager')]" />

    <!-- ── Empty state ─────────────────────────────────────────────── -->
    <template v-if="isEmpty">
        <header class="py-8 pt-16 text-center">
            <h1 class="text-[25px] font-medium antialiased flex justify-center items-center gap-2 sm:gap-3">
                <Icon name="link" class="size-5 text-gray-500 dark:text-gray-400" />
                {{ __('Webhook Manager') }}
            </h1>
        </header>

        <EmptyStateMenu
            :heading="__('webhook-manager::messages.cp.get_started')"
            :subheading="__('webhook-manager::messages.cp.get_started_sub')"
        >
            <EmptyStateItem
                v-if="canCreateOutbound"
                icon="arrow-up-right"
                :heading="__('webhook-manager::messages.cp.create_outbound')"
                :href="createOutboundUrl"
                :description="__('webhook-manager::messages.outbound_create_description')"
            />
            <EmptyStateItem
                v-if="canCreateInbound"
                icon="download"
                :heading="__('webhook-manager::messages.cp.create_inbound')"
                :href="createInboundUrl"
                :description="__('webhook-manager::messages.inbound_create_description')"
            />
            <EmptyStateItem
                v-if="canCreateRule"
                icon="cog"
                :heading="__('webhook-manager::messages.cp.create_rule')"
                :href="createRuleUrl"
                :description="__('webhook-manager::messages.rules_create_description')"
            />
        </EmptyStateMenu>

        <DocsCallout :topic="__('Webhook Manager')" url="https://github.com/goldnead/statamic-webhook-manager#statamic-webhook-manager" />
    </template>

    <!-- ── Populated state ─────────────────────────────────────────── -->
    <div v-else class="max-w-page mx-auto">
        <Header :title="__('Webhook Manager')" icon="link">
            <CommandPaletteItem
                v-if="canCreateOutbound"
                category="Actions"
                :text="__('webhook-manager::messages.cp.create_outbound')"
                icon="arrow-up-right"
                :url="createOutboundUrl"
                v-slot="{ text, url }"
            >
                <Button :href="url" :text="text" variant="primary" />
            </CommandPaletteItem>
        </Header>

        <Alert
            v-if="testResult"
            :variant="testResult.ok ? 'success' : 'error'"
            :heading="testResult.ok ? __('webhook-manager::messages.cp.test_ok') : __('webhook-manager::messages.cp.test_failed')"
            :text="`${testResult.name} — HTTP ${testResult.response_status ?? '—'} — ${testResult.duration_ms ?? '?'} ms${testResult.error_message ? ' — ' + testResult.error_message : ''}`"
            class="mb-4"
            data-testid="overview-test-result"
        />

        <!-- ── Key figures ─────────────────────────────────────────
             A static table (core's dumb `Table`), not a grid of cards:
             one row per figure, the label links to the screen behind it. -->
        <Panel
            :heading="__('webhook-manager::messages.cp.stats_heading')"
            :subheading="__('webhook-manager::messages.cp.stats_sub')"
        >
            <Card>
                <Table>
                    <TableColumns>
                        <TableColumn>{{ __('webhook-manager::messages.cp.stat_metric') }}</TableColumn>
                        <TableColumn>{{ __('webhook-manager::messages.cp.stat_value') }}</TableColumn>
                    </TableColumns>
                    <TableRows>
                        <TableRow v-for="stat in stats" :key="stat.key">
                            <TableCell>
                                <Link :href="stat.url" class="flex items-center gap-2">
                                    <Icon :name="stat.icon" class="size-4 shrink-0 text-gray-500 dark:text-gray-400" />
                                    {{ stat.label }}
                                </Link>
                            </TableCell>
                            <TableCell>
                                <span class="tabular-nums font-medium text-gray-900 dark:text-gray-100">{{ stat.value }}</span>
                            </TableCell>
                        </TableRow>
                    </TableRows>
                </Table>
            </Card>
        </Panel>

        <!-- ── The webhooks themselves ─────────────────────────────── -->
        <Panel
            :heading="__('webhook-manager::messages.cp.webhooks_heading')"
            :subheading="__('webhook-manager::messages.cp.webhooks_sub')"
        >
            <template #header-actions>
                <Button
                    :href="outboundIndexUrl"
                    :text="__('webhook-manager::messages.cp.webhooks_all')"
                    size="sm"
                />
            </template>

            <Listing
                v-if="hasWebhooks"
                :items="webhooks"
                :columns="webhookColumns"
                :action-url="outboundActionUrl"
                :allow-customizing-columns="false"
                :allow-search="false"
                :allow-presets="false"
                :show-pagination-totals="false"
                :show-pagination-page-links="false"
                :show-pagination-per-page-selector="false"
                @refreshing="reloadPage"
            >
                <template #cell-name="{ row: hook }">
                    <Link :href="hook.edit_url" class="font-semibold">{{ hook.name }}</Link>
                    <span class="block text-2xs text-gray-600 dark:text-gray-400">{{ hook.handle }}</span>
                </template>

                <template #cell-trigger_type="{ row: hook }">
                    <Badge color="blue" :text="hook.trigger_label || hook.trigger_type" />
                </template>

                <template #cell-method="{ row: hook }">
                    <Badge :color="methodColor(hook.method)" :text="hook.method" />
                </template>

                <!-- `min-w-64` is the part that holds. The `width` percentage
                     in the column definition is only a hint to an auto-layout
                     table, and the URL column is the one that gives way: the
                     Trigger badge does not shrink, so below roughly 1280px the
                     URL collapsed back to `ht...g`. A floor of 16rem keeps it
                     readable and lets the table scroll instead. `block` so
                     MiddleEllipsis measures the cell, not a shrink-wrapped
                     span. -->
                <template #cell-url="{ value }">
                    <span class="block min-w-64 font-mono text-xs text-gray-900 dark:text-gray-100">
                        <MiddleEllipsis :text="value || ''" />
                    </span>
                </template>

                <template #cell-enabled="{ row: hook }">
                    <Badge
                        :color="hook.enabled ? 'green' : 'default'"
                        :text="hook.enabled ? __('webhook-manager::messages.cp.status_active') : __('webhook-manager::messages.cp.status_disabled')"
                    />
                </template>

                <!-- Only the two things that are not Statamic actions:
                     navigation, and a POST that needs its own result banner.
                     Enable / Disable / Delete come from the action endpoint,
                     so they work for one row and for a checked selection. -->
                <template #prepended-row-actions="{ row: hook }">
                    <DropdownItem
                        v-if="hook.can_edit"
                        icon="cog"
                        :text="__('webhook-manager::messages.cp.action_edit')"
                        :href="hook.edit_url"
                    />
                    <DropdownItem
                        v-if="hook.can_test && hook.test_url"
                        icon="arrow-up-right"
                        :text="__('webhook-manager::messages.cp.action_test')"
                        @click="runTest(hook)"
                    />
                </template>
            </Listing>

            <Card v-else>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('webhook-manager::messages.cp.webhooks_empty') }}
                </p>
            </Card>
        </Panel>

        <!-- ── Recent failures ─────────────────────────────────────── -->
        <Panel
            v-if="hasRecentFailures"
            :heading="__('webhook-manager::messages.cp.failures_heading')"
            :subheading="__('webhook-manager::messages.cp.failures_sub')"
        >
            <template #header-actions>
                <Button
                    :href="deliveriesIndexUrl"
                    :text="__('webhook-manager::messages.cp.failures_all')"
                    size="sm"
                />
            </template>

            <Listing
                :items="recentFailures"
                :columns="failureColumns"
                :action-url="deliveryActionUrl"
                :allow-customizing-columns="false"
                :allow-search="false"
                :allow-presets="false"
                :show-pagination-totals="false"
                :show-pagination-page-links="false"
                :show-pagination-per-page-selector="false"
                @refreshing="reloadPage"
            >
                <template #cell-when="{ row }">
                    <Link v-if="row.show_url" :href="row.show_url">
                        <date-time :of="row.when" />
                    </Link>
                    <date-time v-else :of="row.when" />
                </template>

                <template #cell-trigger="{ row }">
                    <Badge color="blue" :text="row.trigger_label || row.trigger" />
                </template>

                <!-- `min-w-64` is the part that holds. The `width` percentage
                     in the column definition is only a hint to an auto-layout
                     table, and the URL column is the one that gives way: the
                     Trigger badge does not shrink, so below roughly 1280px the
                     URL collapsed back to `ht...g`. A floor of 16rem keeps it
                     readable and lets the table scroll instead. `block` so
                     MiddleEllipsis measures the cell, not a shrink-wrapped
                     span. -->
                <template #cell-url="{ value }">
                    <span class="block min-w-64 font-mono text-xs text-gray-900 dark:text-gray-100">
                        <MiddleEllipsis :text="value || ''" />
                    </span>
                </template>

                <!-- Wording and colour come from the controller. The badge used
                     to be hard-coded red around the raw database enum
                     (`network`, `auth`), while one click further the same field
                     read "Authentifizierungsfehler" in its own colour. -->
                <template #cell-status="{ row }">
                    <Badge :color="row.status_color || 'red'" :text="row.status" />
                </template>

                <template #prepended-row-actions="{ row }">
                    <DropdownItem
                        v-if="row.show_url"
                        icon="arrow-up-right"
                        :text="__('webhook-manager::messages.cp.action_open_delivery')"
                        :href="row.show_url"
                    />
                </template>
            </Listing>
        </Panel>

        <DocsCallout :topic="__('Webhook Manager')" url="https://github.com/goldnead/statamic-webhook-manager#statamic-webhook-manager" />
    </div>
</template>
