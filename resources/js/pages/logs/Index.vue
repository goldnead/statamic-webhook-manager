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
    actionUrl:      { type: String, required: true },
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
        <Head :title="__('Logs')" />

        <!-- ── Empty state ──────────────────────────────────────────── -->
        <div v-if="isEmpty">
            <Header :title="__('Logs')" icon="clipboard" />

            <EmptyStateMenu :heading="__('No logs yet')">
                <EmptyStateItem
                    :heading="__('Nothing logged so far')"
                    :description="__('Logs are written automatically when webhooks are dispatched or received. Check back once some activity has occurred.')"
                    icon="clipboard"
                >
                    <DocsCallout
                        :heading="__('Learn about logs')"
                        url="https://statamic.com/addons/goldnead/webhook-manager/docs/logs"
                    />
                </EmptyStateItem>
            </EmptyStateMenu>
        </div>

        <!-- ── Populated state ─────────────────────────────────────── -->
        <div v-else>
            <Header :title="__('Logs')" icon="clipboard" />

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
                    <span v-else class="text-gray-400">—</span>
                </template>

                <!-- error_type column -->
                <template #cell-error_type="{ row }">
                    <Badge
                        v-if="row.error_type"
                        :color="errorTypeColor(row.error_type)"
                    >
                        {{ errorTypeLabel(row.error_type) }}
                    </Badge>
                    <span v-else class="text-gray-400">—</span>
                </template>

                <!-- created_at column -->
                <template #cell-created_at="{ row }">
                    <date-time :of="row.created_at" />
                </template>
            </Listing>
        </div>
    </div>
</template>
