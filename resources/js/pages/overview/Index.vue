<script setup>
import { computed } from 'vue';
import { Head } from '@statamic/cms/inertia';
import {
    Header,
    Panel,
    Button,
    Badge,
    Icon,
    Listing,
    EmptyStateMenu,
    EmptyStateItem,
    MiddleEllipsis,
    CommandPaletteItem,
} from '@statamic/cms/ui';

/**
 * Overview dashboard.
 *
 * Shows either:
 * - An EmptyStateMenu when there are no outbound webhooks, inbound endpoints
 *   and no rules configured yet (first-run experience).
 * - The populated view: 4 stat cards + a "Recent Failures" Listing + a
 *   Quick-Actions panel.
 */
const props = defineProps({
    stats:              { type: Array,   required: true },
    recentFailures:     { type: Object,  required: true },
    failureColumns:     { type: Array,   required: true },
    isEmpty:            { type: Boolean, default: false },

    // Create URLs
    createOutboundUrl:  { type: String, default: null },
    createInboundUrl:   { type: String, default: null },
    createRuleUrl:      { type: String, default: null },

    // Nav URLs
    outboundUrl:        { type: String, default: null },
    deliveriesUrl:      { type: String, default: null },
    logsUrl:            { type: String, default: null },

    // Permission flags
    canCreateOutbound:  { type: Boolean, default: false },
    canCreateInbound:   { type: Boolean, default: false },
    canCreateRule:      { type: Boolean, default: false },
});

const hasRecentFailures = computed(
    () => props.recentFailures?.data?.length || props.recentFailures?.meta?.total,
);
</script>

<template>
    <Head :title="[__('Overview'), __('Webhook Manager')]" />

    <div class="max-w-page mx-auto">
        <Header :title="__('Webhook Manager')" icon="link">
            <CommandPaletteItem
                v-if="canCreateOutbound"
                category="Actions"
                :text="__('Create Outbound Webhook')"
                icon="outgoing"
                :url="createOutboundUrl"
            />
            <CommandPaletteItem
                v-if="canCreateInbound"
                category="Actions"
                :text="__('Create Inbound Endpoint')"
                icon="incoming"
                :url="createInboundUrl"
            />
        </Header>

        <!-- ── Empty state ──────────────────────────────────────────── -->
        <div v-if="isEmpty">
            <EmptyStateMenu :heading="__('Get started with the Webhook Manager')">
                <EmptyStateItem
                    v-if="canCreateOutbound"
                    icon="outgoing"
                    :heading="__('Create Outbound Webhook')"
                    :href="createOutboundUrl"
                    :description="__('webhook-manager::messages.outbound_create_description')"
                />
                <EmptyStateItem
                    v-if="canCreateInbound"
                    icon="incoming"
                    :heading="__('Create Inbound Endpoint')"
                    :href="createInboundUrl"
                    :description="__('webhook-manager::messages.inbound_create_description')"
                />
                <EmptyStateItem
                    v-if="canCreateRule"
                    icon="cog"
                    :heading="__('Add a Rule')"
                    :href="createRuleUrl"
                    :description="__('webhook-manager::messages.rule_create_description')"
                />
            </EmptyStateMenu>
        </div>

        <!-- ── Populated state ─────────────────────────────────────── -->
        <div v-else class="space-y-6">

            <!-- Stat cards: 4 columns lg / 2 md / 1 sm -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <Panel v-for="stat in stats" :key="stat.key">
                    <div class="p-4 flex items-start gap-3">
                        <div class="size-10 rounded-lg bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400 flex items-center justify-center flex-shrink-0">
                            <Icon :name="stat.icon" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-2xl font-bold">{{ stat.value }}</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400 truncate">{{ stat.label }}</div>
                            <div v-if="stat.trend != null" class="text-xs mt-0.5">
                                <span :class="stat.trend > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'">
                                    {{ stat.trend > 0 ? '↑' : '↓' }} {{ Math.abs(stat.trend) }}%
                                </span>
                                <span class="text-gray-500 ms-1">{{ __('vs last week') }}</span>
                            </div>
                        </div>
                    </div>
                </Panel>
            </div>

            <!-- Recent Failures listing -->
            <Panel
                v-if="hasRecentFailures"
                :heading="__('Recent Failures')"
            >
                <Listing
                    :items="recentFailures"
                    :columns="failureColumns"
                    :allow-bulk-actions="false"
                    :allow-customizing-columns="false"
                >
                    <template #cell-trigger="{ row }">
                        <Badge color="blue" :text="row.trigger_label || row.trigger" />
                    </template>

                    <template #cell-status="{ row }">
                        <Badge color="red" :text="row.status" />
                    </template>

                    <template #cell-when="{ value }">
                        <date-time :of="value" />
                    </template>

                    <template #cell-url="{ value }">
                        <MiddleEllipsis :text="value || ''" class="font-mono text-xs" />
                    </template>
                </Listing>
            </Panel>

            <!-- Quick Actions -->
            <Panel :heading="__('Quick Actions')">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 p-4">
                    <Button :href="outboundUrl" :text="__('All Outbound')" />
                    <Button :href="deliveriesUrl" :text="__('Recent Deliveries')" />
                    <Button :href="logsUrl" :text="__('System Logs')" />
                </div>
            </Panel>

        </div>
    </div>
</template>
