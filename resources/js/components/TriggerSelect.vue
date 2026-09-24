<script setup>
import { computed, ref } from 'vue';
import { Combobox } from '@statamic/cms/ui';
import { buildTriggerRows, isGroupRow } from '../support/triggerChoices.js';

/**
 * The trigger picker: core's searchable Combobox, grouped by the trigger's
 * source type (Einträge, Zahlungen, Kurse …) with the headings as rows.
 *
 * `choices` is TriggerRegistry::groupedOptions(). `options` (handle → label)
 * is the flat fallback for a caller that has not been given the groups; it
 * renders the same picker without headings.
 *
 * The value starts empty on a new record: preselecting the first trigger of
 * the list made "Partner: Provision verdient" the default of every new hook.
 */
const props = defineProps({
    modelValue: { type: String, default: null },
    choices: { type: Array, default: null },
    options: { type: Object, default: () => ({}) },
    id: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const query = ref('');

const source = computed(() => props.choices?.length
    ? props.choices
    : Object.entries(props.options ?? {}).map(([value, label]) => ({ value, label, short_label: label })));

const rows = computed(() => buildTriggerRows(source.value, query.value));

function onPicked(value) {
    // A heading is a row for the eye, not a trigger.
    if (isGroupRow(value)) return;

    emit('update:modelValue', value ?? null);
}

function onSearch(value) {
    query.value = value ?? '';
}

defineExpose({ rows, onPicked });
</script>

<template>
    <Combobox
        :id="id"
        :model-value="modelValue"
        :options="rows"
        searchable
        ignore-filter
        @update:model-value="onPicked"
        @search="onSearch"
    >
        <template #option="option">
            <span
                v-if="option.heading"
                class="block truncate text-xs text-gray-400 dark:text-gray-500"
                data-trigger-group-heading
                v-text="option.label"
            />
            <span v-else class="truncate ps-2" v-text="option.short_label || option.label" />
        </template>
    </Combobox>
</template>
