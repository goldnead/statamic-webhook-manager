<script setup>
import { ref } from 'vue';
import { Head, Link } from '@statamic/cms/inertia';
import { router } from '@inertiajs/vue3';
import { Header, Button, Field, Input, Panel, Badge, Table, TableRows, TableRow, TableCell, EmptyStateMenu, EmptyStateItem } from '@statamic/cms/ui';

const props = defineProps({
    endpoints: { type: Object, required: true },
    createUrl: { type: String, required: true },
    canCreate: { type: Boolean, default: false },
    searchTerm: { type: String, default: '' },
    actionOptions: { type: Object, default: () => ({}) },
    routePrefix: { type: String, default: '' },
});

const search = ref(props.searchTerm);

function applySearch() {
    router.get(window.location.pathname, {
        q: search.value || undefined,
    }, { preserveState: true, preserveScroll: true });
}

function toggle(endpoint) {
    router.patch(endpoint.toggle_url, {}, { preserveScroll: true });
}

function fullUrl(endpoint) {
    const base = props.routePrefix ? `/${props.routePrefix}` : '';
    return `${base}/${endpoint.handle}`;
}
</script>

<template>
    <Head :title="__('Inbound Endpoints')" />

    <Header :title="__('Inbound Endpoints')" icon="incoming">
        <Button v-if="canCreate" variant="primary" :href="createUrl">
            {{ __('Create endpoint') }}
        </Button>
    </Header>

    <Panel class="mb-4">
        <div class="flex gap-3 items-end p-4">
            <Field :label="__('Search')" id="search" class="flex-1">
                <Input id="search" v-model="search" :placeholder="__('Name, handle or path')" @keydown.enter="applySearch" />
            </Field>
            <Button variant="default" @click="applySearch">{{ __('Search') }}</Button>
        </div>
    </Panel>

    <EmptyStateMenu v-if="!endpoints.data.length">
        <EmptyStateItem
            :label="__('Create your first inbound endpoint')"
            icon="incoming"
            :url="createUrl"
        />
    </EmptyStateMenu>

    <Table v-else>
        <TableRows>
            <TableRow header>
                <TableCell>{{ __('Name') }}</TableCell>
                <TableCell>{{ __('URL') }}</TableCell>
                <TableCell>{{ __('Auth') }}</TableCell>
                <TableCell>{{ __('Action') }}</TableCell>
                <TableCell>{{ __('Status') }}</TableCell>
                <TableCell></TableCell>
            </TableRow>
            <TableRow v-for="endpoint in endpoints.data" :key="endpoint.id">
                <TableCell>
                    <Link :href="endpoint.edit_url" class="font-semibold">{{ endpoint.name }}</Link>
                    <div class="text-xs text-gray-600">{{ endpoint.handle }}</div>
                </TableCell>
                <TableCell class="max-w-md truncate">
                    <code class="text-xs">{{ fullUrl(endpoint) }}</code>
                </TableCell>
                <TableCell><code class="text-xs">{{ endpoint.auth_type }}</code></TableCell>
                <TableCell>
                    <span class="text-sm">{{ actionOptions[endpoint.action_type] || endpoint.action_type }}</span>
                </TableCell>
                <TableCell>
                    <Badge :color="endpoint.enabled ? 'green' : 'gray'">
                        {{ endpoint.enabled ? __('Enabled') : __('Disabled') }}
                    </Badge>
                </TableCell>
                <TableCell class="text-right">
                    <Button variant="default" size="sm" @click="toggle(endpoint)">
                        {{ endpoint.enabled ? __('Disable') : __('Enable') }}
                    </Button>
                </TableCell>
            </TableRow>
        </TableRows>
    </Table>
</template>
