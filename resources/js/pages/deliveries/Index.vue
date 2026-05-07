<script setup>
import { ref } from 'vue';
import { Head, Link } from '@statamic/cms/inertia';
import { router } from '@inertiajs/vue3';
import { Header, Panel, Field, Input, Button, Badge, Table, TableRows, TableRow, TableCell, EmptyStateMenu, EmptyStateItem } from '@statamic/cms/ui';

const props = defineProps({
    deliveries: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const status = ref(props.filters.status ?? '');
const trigger = ref(props.filters.trigger ?? '');
const errorType = ref(props.filters.error_type ?? '');

function applyFilters() {
    router.get(window.location.pathname, {
        status: status.value || undefined,
        trigger: trigger.value || undefined,
        error_type: errorType.value || undefined,
    }, { preserveState: true, preserveScroll: true });
}

function statusColor(badge) {
    return {
        success: 'green',
        failed: 'red',
        processing: 'amber',
        pending: 'blue',
        cancelled: 'gray',
    }[badge] ?? 'gray';
}
</script>

<template>
    <Head :title="__('Deliveries')" />

    <Header :title="__('Deliveries')" icon="audit-checked" />

    <Panel class="mb-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 p-4 items-end">
            <Field :label="__('Status')" id="status">
                <select id="status" v-model="status" class="input-text">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="pending">pending</option>
                    <option value="processing">processing</option>
                    <option value="success">success</option>
                    <option value="failed">failed</option>
                    <option value="cancelled">cancelled</option>
                </select>
            </Field>
            <Field :label="__('Trigger')" id="trigger">
                <Input id="trigger" v-model="trigger" />
            </Field>
            <Field :label="__('Error type')" id="error_type">
                <Input id="error_type" v-model="errorType" />
            </Field>
            <Button variant="default" @click="applyFilters">{{ __('Filter') }}</Button>
        </div>
    </Panel>

    <EmptyStateMenu v-if="!deliveries.data.length">
        <EmptyStateItem :label="__('No deliveries match these filters.')" />
    </EmptyStateMenu>

    <Table v-else>
        <TableRows>
            <TableRow header>
                <TableCell>{{ __('Status') }}</TableCell>
                <TableCell>{{ __('Trigger') }}</TableCell>
                <TableCell>{{ __('URL') }}</TableCell>
                <TableCell>{{ __('HTTP') }}</TableCell>
                <TableCell>{{ __('Attempts') }}</TableCell>
                <TableCell>{{ __('When') }}</TableCell>
                <TableCell></TableCell>
            </TableRow>
            <TableRow v-for="delivery in deliveries.data" :key="delivery.id">
                <TableCell>
                    <Badge :color="statusColor(delivery.status_badge)" size="sm">{{ delivery.status }}</Badge>
                </TableCell>
                <TableCell><code>{{ delivery.trigger_type }}</code></TableCell>
                <TableCell class="max-w-md truncate">{{ delivery.request_url }}</TableCell>
                <TableCell>{{ delivery.response_status ?? '—' }}</TableCell>
                <TableCell>{{ delivery.attempts }}</TableCell>
                <TableCell>{{ delivery.created_at_human }}</TableCell>
                <TableCell>
                    <Link :href="delivery.show_url" class="text-blue">{{ __('View') }}</Link>
                </TableCell>
            </TableRow>
        </TableRows>
    </Table>
</template>
