<script setup>
import { Head } from '@statamic/cms/inertia';
import { Header, Alert, Table, TableRows, TableRow, TableCell } from '@statamic/cms/ui';

defineProps({
    templates: { type: Array, default: () => [] },
});
</script>

<template>
    <Head :title="__('Webhook Templates')" />

    <Header :title="__('Templates')" icon="content-writing" />

    <Alert
        v-if="!templates.length"
        variant="default"
        :heading="__('No templates yet')"
        :text="__('Templates let you reuse payload bodies across hooks. CP-side editing arrives in the next iteration; the renderer is already used by outbound webhooks.')"
        class="mb-6"
    />

    <Table v-else>
        <TableRows>
            <TableRow header>
                <TableCell>{{ __('Name') }}</TableCell>
                <TableCell>{{ __('Handle') }}</TableCell>
                <TableCell>{{ __('Type') }}</TableCell>
            </TableRow>
            <TableRow v-for="tpl in templates" :key="tpl.id">
                <TableCell>{{ tpl.name }}</TableCell>
                <TableCell><code>{{ tpl.handle }}</code></TableCell>
                <TableCell>{{ tpl.type }}</TableCell>
            </TableRow>
        </TableRows>
    </Table>
</template>
