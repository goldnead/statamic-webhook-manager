<script setup>
import { ref } from 'vue';
import { Head, Link } from '@statamic/cms/inertia';
import { router } from '@inertiajs/vue3';
import { Header, Button, Field, Input, Panel, Badge, Table, TableRows, TableRow, TableCell, EmptyStateMenu, EmptyStateItem } from '@statamic/cms/ui';

const props = defineProps({
    webhooks: { type: Object, required: true },
    createUrl: { type: String, required: true },
    canCreate: { type: Boolean, default: false },
    searchTerm: { type: String, default: '' },
    triggerOptions: { type: Object, default: () => ({}) },
});

const search = ref(props.searchTerm);

function applySearch() {
    router.get(window.location.pathname, {
        q: search.value || undefined,
    }, { preserveState: true, preserveScroll: true });
}

function toggle(hook) {
    router.patch(hook.toggle_url, {}, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="__('Outbound Webhooks')" />

    <Header :title="__('Outbound Webhooks')" icon="outgoing">
        <Button v-if="canCreate" variant="primary" :href="createUrl">
            {{ __('Create webhook') }}
        </Button>
    </Header>

    <Panel class="mb-4">
        <div class="flex gap-3 items-end p-4">
            <Field :label="__('Search')" id="search" class="flex-1">
                <Input id="search" v-model="search" :placeholder="__('Name, handle or URL')" @keydown.enter="applySearch" />
            </Field>
            <Button variant="default" @click="applySearch">{{ __('Search') }}</Button>
        </div>
    </Panel>

    <EmptyStateMenu v-if="!webhooks.data.length">
        <EmptyStateItem
            :label="__('Create your first outbound webhook')"
            icon="outgoing"
            :url="createUrl"
        />
    </EmptyStateMenu>

    <Table v-else>
        <TableRows>
            <TableRow header>
                <TableCell>{{ __('Name') }}</TableCell>
                <TableCell>{{ __('Trigger') }}</TableCell>
                <TableCell>{{ __('URL') }}</TableCell>
                <TableCell>{{ __('Status') }}</TableCell>
                <TableCell></TableCell>
            </TableRow>
            <TableRow v-for="hook in webhooks.data" :key="hook.id">
                <TableCell>
                    <Link :href="hook.edit_url" class="font-semibold">{{ hook.name }}</Link>
                    <div class="text-xs text-gray-600">{{ hook.handle }}</div>
                </TableCell>
                <TableCell><code>{{ hook.trigger_type }}</code></TableCell>
                <TableCell class="max-w-md truncate">{{ hook.url }}</TableCell>
                <TableCell>
                    <Badge :color="hook.enabled ? 'green' : 'gray'">
                        {{ hook.enabled ? __('Enabled') : __('Disabled') }}
                    </Badge>
                </TableCell>
                <TableCell class="text-right">
                    <Button variant="default" size="sm" @click="toggle(hook)">
                        {{ hook.enabled ? __('Disable') : __('Enable') }}
                    </Button>
                </TableCell>
            </TableRow>
        </TableRows>
    </Table>
</template>
