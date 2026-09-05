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

// Wording and colour for both badges arrive on the row from LogController.
//
// A second copy used to sit here: an English map (`network: 'Network'`,
// `auth: 'Auth'`) keyed on the delivery listing's failure types, which a log
// entry's `type` column never contains. It printed the raw handles
// `inbound_received`, `delivery_failed`, `inbound_auth_failed` at the reader,
// and the level badge next to it printed `info` / `warning` just as raw.
// Its `internal: 'default'` also disagreed with PHP's `internal => 'gray'` —
// the reliable sign that one decision was living in two places.
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
                    <Badge :color="row.level_color || 'default'" :text="row.level_label || row.level" />
                </template>

                <!-- message column — single line, truncated -->
                <template #cell-message="{ row }">
                    <span class="truncate max-w-sm block" :title="row.message">
                        {{ row.message }}
                    </span>
                </template>

                <!-- correlation_id column.
                     `truncate`, not MiddleEllipsis: that component estimates
                     text width from a table it has for one font family
                     (`inter`) and charges a full em per character on a miss —
                     the CP computes `ui-monospace` here, so it always misses.
                     Measured in this very cell: a 109px box, 51px used, seven
                     characters shown where ten and a half fit. `truncate`
                     measures for real, and the start of a correlation id is
                     what one compares against a log line elsewhere. -->
                <template #cell-correlation_id="{ row }">
                    <span
                        v-if="row.correlation_id"
                        class="block truncate font-mono text-sm"
                        :title="row.correlation_id"
                    >{{ row.correlation_id }}</span>
                    <span v-else class="text-gray-500 dark:text-gray-400">—</span>
                </template>

                <!-- log event type column -->
                <template #cell-error_type="{ row }">
                    <Badge
                        v-if="row.error_type"
                        :color="row.error_type_color || 'default'"
                        :text="row.error_type_label || row.error_type"
                    />
                    <span v-else class="text-gray-500 dark:text-gray-400">—</span>
                </template>

                <!-- created_at column -->
                <template #cell-created_at="{ row }">
                    <date-time :of="row.created_at" />
                </template>
            </Listing>
        </div>
    </div>
</template>
