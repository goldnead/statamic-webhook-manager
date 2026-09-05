<script setup>
import { computed } from 'vue';
import { Head } from '@statamic/cms/inertia';
import { router } from '@statamic/cms/inertia';
import {
    Header,
    Badge,
    Icon,
    DropdownItem,
    EmptyStateMenu,
    EmptyStateItem,
    DocsCallout,
    Listing,
    MiddleEllipsis,
} from '@statamic/cms/ui';

/**
 * Log listing.
 *
 * Models the Statamic core "Forms Index" pattern (server-driven Listing)
 * but without a create action — logs are written by the system only.
 *
 * The <Listing> component issues AJAX GETs against listingUrl whenever
 * the user changes search / sort / page / filters, so we don't manage
 * pagination state here ourselves.
 */
const props = defineProps({
    logs:           { type: Object, required: true },
    initialColumns: { type: Array,  required: true },
    listingUrl:     { type: String, required: true },
    // Null on purpose — see the controller. There is no bulk-action endpoint
    // for this listing, and a non-null value here is what makes core draw a
    // checkbox column whose menu can never fill.
    actionUrl: { type: String, default: null },
});

const isEmpty = computed(
    () => !props.logs?.data?.length && !props.logs?.meta?.total,
);

const reloadPage = () => router.reload({ only: ['logs'] });

// ── Colour helpers ────────────────────────────────────────────────────────
// Centralised here (not in PHP) so colours stay in sync with Statamic's
// dark-mode-aware Badge component.

const levelColor = (level) => ({
    error:   'red',
    warning: 'amber',
    info:    'blue',
    debug:   'default',
}[level] ?? 'default');

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

// Human-readable labels for error_type badges.
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
        <Head :title="__('webhook-manager::messages.cp.page_logs')" />

        <!-- ── Empty state ──────────────────────────────────────────── -->
        <div v-if="isEmpty">
            <Header :title="__('webhook-manager::messages.cp.page_logs')" icon="clipboard" />

            <EmptyStateMenu :heading="__('webhook-manager::messages.cp.logs_empty_heading')">
                <EmptyStateItem
                    :heading="__('webhook-manager::messages.cp.logs_empty_item')"
                    :description="__('webhook-manager::messages.cp.logs_empty_sub')"
                    icon="clipboard"
                >
                    <DocsCallout
                        :heading="__('webhook-manager::messages.cp.logs_docs')"
                        url="https://github.com/goldnead/statamic-webhook-manager#concepts"
                    />
                </EmptyStateItem>
            </EmptyStateMenu>
        </div>

        <!-- ── Populated state ─────────────────────────────────────── -->
        <div v-else>
            <Header :title="__('webhook-manager::messages.cp.page_logs')" icon="clipboard" />

            <Listing
                :url="listingUrl"
                :columns="initialColumns"
                :action-url="actionUrl"
                :data="logs"
                @updated="reloadPage"
            >
                <!-- level column -->
                <template #cell-level="{ row }">
                    <Badge :color="levelColor(row.level)">
                        {{ row.level }}
                    </Badge>
                </template>

                <!-- message column — single line, truncated -->
                <template #cell-message="{ row }">
                    <span class="truncate max-w-sm block" :title="row.message">
                        {{ row.message }}
                    </span>
                </template>

                <!-- correlation_id column — mono + middle ellipsis -->
                <template #cell-correlation_id="{ row }">
                    <MiddleEllipsis
                        v-if="row.correlation_id"
                        :text="row.correlation_id"
                        class="font-mono text-sm"
                    />
                    <span v-else class="text-gray-500 dark:text-gray-400 dark:text-gray-400">—</span>
                </template>

                <!-- error_type column -->
                <template #cell-error_type="{ row }">
                    <Badge
                        v-if="row.error_type"
                        :color="errorTypeColor(row.error_type)"
                    >
                        {{ errorTypeLabel(row.error_type) }}
                    </Badge>
                    <span v-else class="text-gray-500 dark:text-gray-400 dark:text-gray-400">—</span>
                </template>

                <!-- created_at column -->
                <template #cell-created_at="{ row }">
                    <date-time :of="row.created_at" />
                </template>
            </Listing>
        </div>
    </div>
</template>
