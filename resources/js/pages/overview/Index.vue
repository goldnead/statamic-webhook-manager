<script setup>
import { computed } from 'vue';
import { Head, Link } from '@statamic/cms/inertia';
import {
    Header,
    Panel,
    Badge,
    Icon,
    Listing,
    EmptyStateMenu,
    EmptyStateItem,
    DocsCallout,
    MiddleEllipsis,
} from '@statamic/cms/ui';
import StatTile from '../../components/StatTile.vue';

/**
 * Overview dashboard.
 *
 * Layout follows Statamic core's pages/Dashboard.vue: a simple flex/wrap
 * container that uses CONTAINER queries (@container) rather than viewport
 * breakpoints, so cards reflow correctly when the CP nav is open and the
 * available width is less than the viewport's `lg` breakpoint.
 *
 * Two states:
 *   - Empty (0 outbound + 0 inbound + 0 rules): centered Statamic-style
 *     header with icon next to the title, EmptyStateMenu of getting-started
 *     CTAs.
 *   - Populated: full Header, stat cards in a container-query grid,
 *     Recent Failures listing, DocsCallout footer.
 */
const props = defineProps({
    stats:              { type: Array,   required: true },
    recentFailures:     { type: Array,   default: () => [] },
    failureColumns:     { type: Array,   required: true },
    isEmpty:            { type: Boolean, default: false },

    // Create URLs
    createOutboundUrl:  { type: String, default: null },
    createInboundUrl:   { type: String, default: null },
    createRuleUrl:      { type: String, default: null },

    // Permission flags
    canCreateOutbound:  { type: Boolean, default: false },
    canCreateInbound:   { type: Boolean, default: false },
    canCreateRule:      { type: Boolean, default: false },
});

const hasRecentFailures = computed(() => props.recentFailures?.length > 0);
</script>

<template>
    <Head :title="[__('Overview'), __('Webhook Manager')]" />

    <!-- ── Empty state ─────────────────────────────────────────────── -->
    <template v-if="isEmpty">
        <header class="py-8 pt-16 text-center">
            <h1 class="text-[25px] font-medium antialiased flex justify-center items-center gap-2 sm:gap-3">
                <Icon name="link" class="size-5 text-gray-500" />
                {{ __('Webhook Manager') }}
            </h1>
        </header>

        <EmptyStateMenu
            :heading="__('Get started with the Webhook Manager')"
            :subheading="__('Send notifications to external services, accept incoming requests, and run automation rules — all from inside Statamic.')"
        >
            <EmptyStateItem
                v-if="canCreateOutbound"
                icon="arrow-up-right"
                :heading="__('Create Outbound Webhook')"
                :href="createOutboundUrl"
                :description="__('webhook-manager::messages.outbound_create_description')"
            />
            <EmptyStateItem
                v-if="canCreateInbound"
                icon="download"
                :heading="__('Create Inbound Endpoint')"
                :href="createInboundUrl"
                :description="__('webhook-manager::messages.inbound_create_description')"
            />
            <EmptyStateItem
                v-if="canCreateRule"
                icon="cog"
                :heading="__('Add a Rule')"
                :href="createRuleUrl"
                :description="__('webhook-manager::messages.rules_create_description')"
            />
        </EmptyStateMenu>

        <DocsCallout :topic="__('Webhook Manager')" url="https://statamic.dev/" />
    </template>

    <!-- ── Populated state ─────────────────────────────────────────── -->
    <template v-else>
        <Header :title="__('Webhook Manager')" icon="link" />

        <!-- Compact native stat tiles (shared StatTile). An auto-fit grid
             keeps this to one CSS class so it reflows responsively without
             relying on breakpoint utilities that Statamic core defines
             unlayered (a layered override would lose the cascade). -->
        <div class="grid grid-cols-[repeat(auto-fit,minmax(220px,1fr))] gap-4 mt-4">
            <StatTile
                v-for="stat in stats"
                :key="stat.key"
                :label="stat.label"
                :value="stat.value"
                :icon="stat.icon"
            />
        </div>

        <!-- Recent Failures listing -->
        <Panel
            v-if="hasRecentFailures"
            :heading="__('Recent Failures')"
            :subheading="__('Last 8 failed deliveries — investigate or replay them.')"
            class="mt-6"
        >
            <Listing
                :items="recentFailures"
                :columns="failureColumns"
                :allow-bulk-actions="false"
                :allow-customizing-columns="false"
                :allow-search="false"
                :allow-presets="false"
                :show-pagination-totals="false"
                :show-pagination-page-links="false"
                :show-pagination-per-page-selector="false"
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

                <template #cell-url="{ value }">
                    <span class="font-mono text-xs text-gray-700 dark:text-gray-300">
                        <MiddleEllipsis :text="value || ''" />
                    </span>
                </template>

                <template #cell-status="{ row }">
                    <Badge color="red" :text="row.status" />
                </template>
            </Listing>
        </Panel>

        <DocsCallout :topic="__('Webhook Manager')" url="https://statamic.dev/" />
    </template>
</template>
