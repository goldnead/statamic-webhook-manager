<script setup>
import { Head } from '@statamic/cms/inertia';
import { Header, Alert, Table, TableRows, TableRow, TableCell, Badge } from '@statamic/cms/ui';

defineProps({
    rules: { type: Array, default: () => [] },
});
</script>

<template>
    <Head :title="__('Webhook Rules')" />

    <Header :title="__('Rules')" icon="instructions" />

    <Alert
        variant="warning"
        :heading="__('Coming in the next iteration')"
        :text="__('Rules let you compose triggers, conditions and multiple actions. Schema, contracts and the condition evaluator are in place; the rule engine itself ships next.')"
        class="mb-6"
    />

    <Table v-if="rules.length">
        <TableRows>
            <TableRow header>
                <TableCell>{{ __('Name') }}</TableCell>
                <TableCell>{{ __('Trigger') }}</TableCell>
                <TableCell>{{ __('Status') }}</TableCell>
            </TableRow>
            <TableRow v-for="rule in rules" :key="rule.id">
                <TableCell>{{ rule.name }}</TableCell>
                <TableCell><code>{{ rule.trigger_type }}</code></TableCell>
                <TableCell>
                    <Badge :color="rule.enabled ? 'green' : 'gray'">
                        {{ rule.enabled ? __('Enabled') : __('Disabled') }}
                    </Badge>
                </TableCell>
            </TableRow>
        </TableRows>
    </Table>
</template>
