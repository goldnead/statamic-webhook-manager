<script setup>
import { Head } from '@statamic/cms/inertia';
import { Header, Alert, Table, TableRows, TableRow, TableCell, Badge } from '@statamic/cms/ui';

defineProps({
    endpoints: { type: Array, default: () => [] },
});
</script>

<template>
    <Head :title="__('Inbound Endpoints')" />

    <Header :title="__('Inbound Endpoints')" icon="incoming" />

    <Alert
        variant="warning"
        :heading="__('Coming in the next iteration')"
        :text="__('The inbound endpoint controller currently returns 501 Not Implemented. Schema, controller, auth verifiers and mapping engine are scaffolded; full functionality ships in v0.2.')"
        class="mb-6"
    />

    <Table v-if="endpoints.length">
        <TableRows>
            <TableRow header>
                <TableCell>{{ __('Name') }}</TableCell>
                <TableCell>{{ __('Path') }}</TableCell>
                <TableCell>{{ __('Auth') }}</TableCell>
                <TableCell>{{ __('Status') }}</TableCell>
            </TableRow>
            <TableRow v-for="endpoint in endpoints" :key="endpoint.id">
                <TableCell>{{ endpoint.name }}</TableCell>
                <TableCell><code>{{ endpoint.path }}</code></TableCell>
                <TableCell>{{ endpoint.auth_type }}</TableCell>
                <TableCell>
                    <Badge :color="endpoint.enabled ? 'green' : 'gray'">
                        {{ endpoint.enabled ? __('Enabled') : __('Disabled') }}
                    </Badge>
                </TableCell>
            </TableRow>
        </TableRows>
    </Table>
</template>
