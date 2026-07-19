<script setup>
import { computed } from 'vue';
import { Head } from '@statamic/cms/inertia';
import { router } from '@statamic/cms/inertia';
import {
    Header,
    Badge,
    DropdownItem,
    EmptyStateMenu,
    EmptyStateItem,
    DocsCallout,
    Listing,
    MiddleEllipsis,
} from '@statamic/cms/ui';

/**
 * Delivery listing.
 *
 * Server-driven <Listing> pattern (same as Logs/Index). No create
 * action — deliveries are written by the system. Filters are resolved
 * server-side so the controller stays the single source of truth.
 *
 * error_type vocabulary is identical to LogController so colours/labels
 * are kept in sync below.
 */
const props = defineProps({
    deliveries:     { type: Object, required: true },
    initialColumns: { type: Array,  required: true },
    listingUrl:     { type: String, required: true },
    actionUrl:      { type: String, required: true },
});

const isEmpty = computed(
    () => !props.deliveries?.data?.length && !props.deliveries?.meta?.total,
);

const reloadPage = () => router.reload({ only: ['deliveries'] });

// ── Colour helpers ──────────────────────────────────────────────────────────

/** Delivery status → Statamic Badge colour token. */
const statusColor = (status) => ({
    success: 'green',
    failed:  'red',
    pending: 'amber',
    retry:   'amber',
}[status] ?? 'default');

/** HTTP method → Statamic Badge colour token (mirrors Outbound/Index). */
const methodColor = (method) => ({
    GET:    'blue',
    POST:   'green',
    PUT:    'amber',
    PATCH:  'amber',
    DELETE: 'red',
}[(method || '').toUpperCase()] ?? 'default');

/**
 * error_type colour mapping — identical to Logs/Index so the two pages
 * remain visually consistent for operators debugging across both views.
 */
const errorTypeColor = (type) => ({
    network:       'orange',
    timeout:       'amber',
    auth:          'red',
    client:        'yellow',
    server:        'red',
    payload:       'purple',
    configuration: 'blue',
    internal:      'default',
}[type] ?? 'default');

const errorTypeLabel = (type) => ({
    network:       'Network',
    timeout:       'Timeout',
    auth:          'Auth',
    client:        'Client',
    server:        'Server',
    payload:       'Payload',
    configuration: 'Config',
    internal:      'Internal',
}[type] ?? type);
</script>

<template>
    <div>
        <Head :title="[__('Deliveries'), __('Webhook Manager')]" />

        <!-- ── Empty state ─────────────────────────────────────────────── -->
        <div v-if="isEmpty" class="max-w-page mx-auto">
            <Header :title="__('Deliveries')" icon="arrow-up-right" />

            <EmptyStateMenu :heading="__('No deliveries yet')">
                <EmptyStateItem
                    :heading="__('Nothing dispatched so far')"
                    :description="__('Deliveries are recorded automatically when outbound webhooks are fired. Check back once some activity has occurred.')"
                    icon="arrow-up-right"
                />
            </EmptyStateMenu>

            <DocsCallout
                :topic="__('Deliveries')"
                url="https://statamic.com/addons/goldnead/webhook-manager/docs/deliveries"
            />
        </div>

        <!-- ── Populated state ─────────────────────────────────────────── -->
        <div v-else class="max-w-page mx-auto">
            <Header :title="__('Deliveries')" icon="arrow-up-right" />

            <Listing
                :url="listingUrl"
                :columns="initialColumns"
                :action-url="actionUrl"
                :data="deliveries"
                preferences-prefix="webhook-manager.deliveries"
                push-query
                @updated="reloadPage"
            >
                <!-- status column -->
                <template #cell-status="{ row }">
                    <Badge :color="statusColor(row.status)" :text="row.status" />
                </template>

                <!-- outbound / trigger name column — links to detail page -->
                <template #cell-outbound_name="{ row }">
                    <a
                        v-if="row.show_url"
                        :href="row.show_url"
                        class="font-semibold hover:text-blue-600"
                    >{{ row.outbound_name || row.trigger_type || '—' }}</a>
                    <span v-else>{{ row.outbound_name || row.trigger_type || '—' }}</span>
                    <span
                        v-if="row.trigger_type"
                        class="block text-2xs text-gray-500 dark:text-gray-400"
                    >{{ row.trigger_type }}</span>
                </template>

                <!-- url column — mono + middle ellipsis -->
                <template #cell-url="{ row }">
                    <span class="font-mono text-xs text-gray-700 dark:text-gray-300">
                        <MiddleEllipsis :text="row.url || ''" />
                    </span>
                </template>

                <!-- method column -->
                <template #cell-method="{ row }">
                    <Badge :color="methodColor(row.method)" :text="row.method" />
                </template>

                <!-- response_code column -->
                <template #cell-response_code="{ row }">
                    <span class="text-gray-600 dark:text-gray-400 tabular-nums">
                        {{ row.response_code || '—' }}
                    </span>
                </template>

                <!-- attempts column -->
                <template #cell-attempts="{ row }">
                    <span class="text-gray-600 dark:text-gray-400 tabular-nums">
                        {{ row.attempts ?? '—' }}
                    </span>
                </template>

                <!-- error_type column (optional — only shown when column visible) -->
                <template #cell-error_type="{ row }">
                    <Badge
                        v-if="row.error_type"
                        :color="errorTypeColor(row.error_type)"
                        :text="errorTypeLabel(row.error_type)"
                    />
                    <span v-else class="text-gray-400">—</span>
                </template>

                <!-- when column -->
                <template #cell-when="{ row }">
                    <date-time :of="row.created_at" />
                </template>

                <!-- row actions -->
                <template #prepended-row-actions="{ row }">
                    <DropdownItem
                        icon="eye"
                        :text="__('View')"
                        :href="row.show_url"
                    />
                    <DropdownItem
                        v-if="row.can_replay"
                        icon="sync"
                        :text="__('Replay')"
                        @click="row.replay_url && router.post(row.replay_url, {}, { preserveScroll: true })"
                    />
                </template>
            </Listing>

            <DocsCallout
                :topic="__('Deliveries')"
                url="https://statamic.com/addons/goldnead/webhook-manager/docs/deliveries"
            />
        </div>
    </div>
</template>
