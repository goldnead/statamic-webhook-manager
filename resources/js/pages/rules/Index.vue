<script setup>
import { ref } from 'vue';
import { Head, Link } from '@statamic/cms/inertia';
import { router } from '@inertiajs/vue3';
import { Header, Button, Field, Input, Panel, Badge, Table, TableRows, TableRow, TableCell, EmptyStateMenu, EmptyStateItem } from '@statamic/cms/ui';

const props = defineProps({
    rules: { type: Object, required: true },
    createUrl: { type: String, required: true },
    canCreate: { type: Boolean, default: false },
    searchTerm: { type: String, default: '' },
    triggerOptions: { type: Object, default: () => ({}) },
    actionOptions: { type: Object, default: () => ({}) },
});

const search = ref(props.searchTerm);

function applySearch() {
    router.get(window.location.pathname, {
        q: search.value || undefined,
    }, { preserveState: true, preserveScroll: true });
}

function toggle(rule) {
    router.patch(rule.toggle_url, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="__('Webhook Rules')" />

    <Header :title="__('Rules')" icon="instructions">
        <Button v-if="canCreate" variant="primary" :href="createUrl">
            {{ __('Create rule') }}
        </Button>
    </Header>

    <Panel class="mb-4">
        <div class="flex gap-3 items-end p-4">
            <Field :label="__('Search')" id="search" class="flex-1">
                <Input id="search" v-model="search" :placeholder="__('Name, handle or trigger')" @keydown.enter="applySearch" />
            </Field>
            <Button variant="default" @click="applySearch">{{ __('Search') }}</Button>
        </div>
    </Panel>

    <EmptyStateMenu v-if="!rules.data.length">
        <EmptyStateItem
            :label="__('Create your first rule')"
            icon="instructions"
            :url="createUrl"
        />
    </EmptyStateMenu>

    <Table v-else>
        <TableRows>
            <TableRow header>
                <TableCell>{{ __('Name') }}</TableCell>
                <TableCell>{{ __('Trigger') }}</TableCell>
                <TableCell>{{ __('Actions') }}</TableCell>
                <TableCell>{{ __('Order') }}</TableCell>
                <TableCell>{{ __('Status') }}</TableCell>
                <TableCell></TableCell>
            </TableRow>
            <TableRow v-for="rule in rules.data" :key="rule.id">
                <TableCell>
                    <Link :href="rule.edit_url" class="font-semibold">{{ rule.name }}</Link>
                    <div class="text-xs text-gray-600">{{ rule.handle }}</div>
                </TableCell>
                <TableCell>
                    <code class="text-xs">{{ rule.trigger_type }}</code>
                    <div v-if="triggerOptions[rule.trigger_type]" class="text-xs text-gray-600">
                        {{ triggerOptions[rule.trigger_type] }}
                    </div>
                </TableCell>
                <TableCell>{{ rule.action_count }}</TableCell>
                <TableCell>{{ rule.order_index }}</TableCell>
                <TableCell>
                    <Badge :color="rule.enabled ? 'green' : 'gray'">
                        {{ rule.enabled ? __('Enabled') : __('Disabled') }}
                    </Badge>
                </TableCell>
                <TableCell class="text-right">
                    <Button variant="default" size="sm" @click="toggle(rule)">
                        {{ rule.enabled ? __('Disable') : __('Enable') }}
                    </Button>
                </TableCell>
            </TableRow>
        </TableRows>
    </Table>
</template>
