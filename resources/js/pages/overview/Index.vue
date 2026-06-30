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

/**
 * Map a stat key to its accent colour token. Mirrors Statamic Badge
 * colour conventions so the icon chip inherits the page's visual language
 * (green for healthy / amber for warnings / red for failures).
 */
function statAccent(key) {
    if (key.includes('failures')) return { ring: 'ring-red-200 dark:ring-red-900/40', bg: 'bg-red-50 dark:bg-red-950/40', text: 'text-red-600 dark:text-red-400' };
    if (key.includes('success_rate')) return { ring: 'ring-green-200 dark:ring-green-900/40', bg: 'bg-green-50 dark:bg-green-950/40', text: 'text-green-600 dark:text-green-400' };
    if (key.includes('inbound')) return { ring: 'ring-purple-200 dark:ring-purple-900/40', bg: 'bg-purple-50 dark:bg-purple-950/40', text: 'text-purple-600 dark:text-purple-400' };
    return { ring: 'ring-blue-200 dark:ring-blue-900/40', bg: 'bg-blue-50 dark:bg-blue-950/40', text: 'text-blue-600 dark:text-blue-400' };
}
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

        <!-- Stat cards: container-query layout — 1col / 2col / 4col based on
             the container width, NOT the viewport. Matches the Statamic
             core Dashboard.vue pattern. -->
        <div class="@container/stats mt-4">
            <div class="grid grid-cols-1 gap-4 @md/stats:grid-cols-2 @4xl/stats:grid-cols-4">
                <div
                    v-for="stat in stats"
                    :key="stat.key"
                >
                    <Panel>
                        <div class="p-5 flex items-center gap-4">
                            <div
                                class="size-11 rounded-lg flex items-center justify-center flex-shrink-0 ring-1"
                                :class="[statAccent(stat.key).ring, statAccent(stat.key).bg, statAccent(stat.key).text]"
                            >
                                <Icon :name="stat.icon" class="size-5" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-2xl font-bold leading-tight tabular-nums">{{ stat.value }}</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400 truncate">{{ stat.label }}</div>
                            </div>
                        </div>
                    </Panel>
                </div>
            </div>
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
