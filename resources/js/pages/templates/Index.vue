<script setup>
import { ref } from 'vue';
import { Head, Link } from '@statamic/cms/inertia';
import { router } from '@inertiajs/vue3';
import { Header, Button, Field, Input, Panel, Badge, Table, TableRows, TableRow, TableCell, EmptyStateMenu, EmptyStateItem } from '@statamic/cms/ui';

const props = defineProps({
    templates: { type: Object, required: true },
    createUrl: { type: String, required: true },
    canCreate: { type: Boolean, default: false },
    searchTerm: { type: String, default: '' },
    typeFilter: { type: String, default: '' },
    typeOptions: { type: Object, default: () => ({}) },
});

const search = ref(props.searchTerm);
const type = ref(props.typeFilter);

function applyFilters() {
    router.get(window.location.pathname, {
        q: search.value || undefined,
        type: type.value || undefined,
    }, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <Head :title="__('Webhook Templates')" />

    <Header :title="__('Templates')" icon="content-writing">
        <Button v-if="canCreate" variant="primary" :href="createUrl">
            {{ __('Create template') }}
        </Button>
    </Header>

    <Panel class="mb-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end p-4">
            <Field :label="__('Search')" id="search" class="md:col-span-2">
                <Input id="search" v-model="search" :placeholder="__('Name or handle')" @keydown.enter="applyFilters" />
            </Field>
            <Field :label="__('Type')" id="type">
                <select id="type" v-model="type" class="input-text" @change="applyFilters">
                    <option value="">{{ __('All types') }}</option>
                    <option v-for="(label, value) in typeOptions" :key="value" :value="value">{{ label }}</option>
                </select>
            </Field>
        </div>
    </Panel>

    <EmptyStateMenu v-if="!templates.data.length">
        <EmptyStateItem
            :label="__('Create your first template')"
            icon="content-writing"
            :url="createUrl"
        />
    </EmptyStateMenu>

    <Table v-else>
        <TableRows>
            <TableRow header>
                <TableCell>{{ __('Name') }}</TableCell>
                <TableCell>{{ __('Handle') }}</TableCell>
                <TableCell>{{ __('Type') }}</TableCell>
                <TableCell></TableCell>
            </TableRow>
            <TableRow v-for="tpl in templates.data" :key="tpl.id">
                <TableCell>
                    <Link :href="tpl.edit_url" class="font-semibold">{{ tpl.name }}</Link>
                </TableCell>
                <TableCell><code class="text-xs">{{ tpl.handle }}</code></TableCell>
                <TableCell>
                    <Badge color="gray">{{ typeOptions[tpl.type] || tpl.type }}</Badge>
                </TableCell>
                <TableCell class="text-right">
                    <Link :href="tpl.edit_url" class="text-sm">{{ __('Edit') }}</Link>
                </TableCell>
            </TableRow>
        </TableRows>
    </Table>
</template>
