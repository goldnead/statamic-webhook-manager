<script setup>
import { ref } from 'vue';
import { Head } from '@statamic/cms/inertia';
import { router } from '@inertiajs/vue3';
import { Header, Panel, Field, Input, Button, Badge, Table, TableRows, TableRow, TableCell, EmptyStateMenu, EmptyStateItem } from '@statamic/cms/ui';

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const level = ref(props.filters.level ?? '');
const type = ref(props.filters.type ?? '');
const correlationId = ref(props.filters.correlation_id ?? '');

function applyFilters() {
    router.get(window.location.pathname, {
        level: level.value || undefined,
        type: type.value || undefined,
        correlation_id: correlationId.value || undefined,
    }, { preserveState: true, preserveScroll: true });
}

function levelColor(lvl) {
    return {
        debug: 'gray',
        info: 'blue',
        warning: 'amber',
        error: 'red',
    }[lvl] ?? 'gray';
}
</script>

<template>
    <Head :title="__('Webhook Logs')" />

    <Header :title="__('Logs')" icon="text-document" />

    <Panel class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 p-4 items-end">
            <Field :label="__('Level')" id="level">
                <select id="level" v-model="level" class="input-text">
                    <option value="">{{ __('All levels') }}</option>
                    <option value="debug">debug</option>
                    <option value="info">info</option>
                    <option value="warning">warning</option>
                    <option value="error">error</option>
                </select>
            </Field>
            <Field :label="__('Type')" id="type">
                <Input id="type" v-model="type" />
            </Field>
            <Field :label="__('Correlation ID')" id="correlation">
                <Input id="correlation" v-model="correlationId" />
            </Field>
            <Button variant="default" @click="applyFilters">{{ __('Filter') }}</Button>
        </div>
    </Panel>

    <EmptyStateMenu v-if="!logs.data.length">
        <EmptyStateItem :label="__('No log entries match these filters.')" />
    </EmptyStateMenu>

    <Table v-else>
        <TableRows>
            <TableRow header>
                <TableCell>{{ __('Level') }}</TableCell>
                <TableCell>{{ __('Type') }}</TableCell>
                <TableCell>{{ __('Message') }}</TableCell>
                <TableCell>{{ __('When') }}</TableCell>
            </TableRow>
            <TableRow v-for="log in logs.data" :key="log.id">
                <TableCell>
                    <Badge :color="levelColor(log.level)" size="sm">{{ log.level }}</Badge>
                </TableCell>
                <TableCell><code>{{ log.type }}</code></TableCell>
                <TableCell class="max-w-xl truncate">{{ log.message }}</TableCell>
                <TableCell>{{ log.created_at_human }}</TableCell>
            </TableRow>
        </TableRows>
    </Table>
</template>
