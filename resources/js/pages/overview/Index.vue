<script setup>
import { Head, Link } from '@statamic/cms/inertia';
import { Header, Panel, Badge, Table, TableRows, TableRow, TableCell, EmptyStateMenu, EmptyStateItem } from '@statamic/cms/ui';

defineProps({
    activeOutbound: { type: Number, default: 0 },
    activeInbound: { type: Number, default: 0 },
    counts: { type: Object, required: true },
    successRate24h: { type: Number, default: 0 },
    successRate7d: { type: Number, default: 0 },
    recentFailures: { type: Array, default: () => [] },
});
</script>

<template>
    <Head :title="__('Webhooks Overview')" />

    <Header :title="__('Webhooks')" icon="hyperlink" />

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <Panel :heading="__('Outbound (active)')">
            <div class="text-3xl font-semibold">{{ activeOutbound }}</div>
        </Panel>
        <Panel :heading="__('Inbound (active)')">
            <div class="text-3xl font-semibold">{{ activeInbound }}</div>
        </Panel>
        <Panel :heading="__('Success rate 24h')">
            <div class="text-3xl font-semibold">{{ successRate24h }}%</div>
        </Panel>
        <Panel :heading="__('Success rate 7d')">
            <div class="text-3xl font-semibold">{{ successRate7d }}%</div>
        </Panel>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <Panel :heading="__('Successful deliveries')">
            <div class="text-2xl">{{ counts.success }}</div>
        </Panel>
        <Panel :heading="__('Failed deliveries')">
            <div class="text-2xl">{{ counts.failed }}</div>
        </Panel>
        <Panel :heading="__('Pending / processing')">
            <div class="text-2xl">{{ counts.pending }}</div>
        </Panel>
    </div>

    <Panel :heading="__('Recent failures')" :subheading="__('Last 8 failed deliveries')">
        <EmptyStateMenu v-if="!recentFailures.length">
            <EmptyStateItem :label="__('All recent deliveries succeeded — nothing to investigate.')" />
        </EmptyStateMenu>
        <Table v-else>
            <TableRows>
                <TableRow header>
                    <TableCell>{{ __('Trigger') }}</TableCell>
                    <TableCell>{{ __('URL') }}</TableCell>
                    <TableCell>{{ __('Error') }}</TableCell>
                    <TableCell>{{ __('When') }}</TableCell>
                    <TableCell></TableCell>
                </TableRow>
                <TableRow v-for="failure in recentFailures" :key="failure.id">
                    <TableCell><code>{{ failure.trigger_type }}</code></TableCell>
                    <TableCell class="max-w-md truncate">{{ failure.request_url }}</TableCell>
                    <TableCell>
                        <Badge color="red" size="sm">{{ failure.error_type ?? '—' }}</Badge>
                    </TableCell>
                    <TableCell>{{ failure.created_at_human }}</TableCell>
                    <TableCell>
                        <Link :href="failure.show_url" class="text-blue">{{ __('View') }}</Link>
                    </TableCell>
                </TableRow>
            </TableRows>
        </Table>
    </Panel>
</template>
