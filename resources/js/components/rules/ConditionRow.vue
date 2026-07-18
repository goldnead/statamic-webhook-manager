<script setup>
import { computed } from 'vue';
import { Button, Input, Select } from '@statamic/cms/ui';

/**
 * A single condition leaf — { field, op, value }.
 *
 * Field shortcuts (recognised by the PHP-side ConditionEvaluator):
 * `site`, `locale`, `trigger`, `replay`. Everything else is treated as a
 * dot-notation path into the trigger payload.
 *
 * For `in` / `not_in` the value input is comma-separated and emitted as
 * an array. For `exists` / `empty` no value is emitted.
 */

const props = defineProps({
    modelValue: { type: Object, required: true },
});
const emit = defineEmits(['update:modelValue', 'remove']);

const FIELD_SHORTCUTS = ['site', 'locale', 'trigger', 'replay'];

const OPS = [
    { value: 'equals',     label: 'equals' },
    { value: 'not_equals', label: 'not equals' },
    { value: 'in',         label: 'is one of' },
    { value: 'not_in',     label: 'is not one of' },
    { value: 'contains',   label: 'contains' },
    { value: 'exists',     label: 'is set' },
    { value: 'empty',      label: 'is empty' },
    { value: 'gt',         label: 'greater than' },
    { value: 'gte',        label: 'greater or equal' },
    { value: 'lt',         label: 'less than' },
    { value: 'lte',        label: 'less or equal' },
    { value: 'regex',      label: 'matches regex' },
];

// Statamic's <Select> wraps <Combobox>, which expects `:options` as an
// array of { value, label } objects. Labels run through __() so they stay
// translatable rather than hard-coded English.
const opOptions = computed(() => OPS.map(o => ({ value: o.value, label: __(o.label) })));

const showsValue = computed(() => !['exists', 'empty'].includes(props.modelValue.op));
const isList = computed(() => ['in', 'not_in'].includes(props.modelValue.op));

const valueInput = computed({
    get() {
        const v = props.modelValue.value;
        if (isList.value) {
            return Array.isArray(v) ? v.join(', ') : (v ?? '');
        }
        return v ?? '';
    },
    set(input) {
        update({ value: isList.value
            ? input.split(',').map(s => s.trim()).filter(s => s !== '')
            : input
        });
    },
});

function update(patch) {
    const next = { ...props.modelValue, ...patch };
    // Strip value when the op doesn't need one — keeps the JSON tidy
    // and matches what the PHP-side evaluator actually reads.
    if (!showsValue.value) {
        delete next.value;
    }
    emit('update:modelValue', next);
}
</script>

<template>
    <div class="flex gap-2 items-start">
        <Input
            type="text"
            class="flex-1"
            list="condition-field-shortcuts"
            :model-value="modelValue.field"
            @update:model-value="update({ field: $event })"
            placeholder="data.status, site, payload.email …"
        />
        <datalist id="condition-field-shortcuts">
            <option v-for="s in FIELD_SHORTCUTS" :key="s" :value="s" />
        </datalist>

        <div class="w-44 shrink-0">
            <Select
                :model-value="modelValue.op"
                :options="opOptions"
                @update:model-value="update({ op: $event })"
            />
        </div>

        <Input
            v-if="showsValue"
            type="text"
            class="flex-1"
            :model-value="valueInput"
            @update:model-value="valueInput = $event"
            :placeholder="isList ? __('comma, separated, values') : __('value')"
        />
        <span v-else class="text-xs text-gray-500 dark:text-gray-400 italic w-32 self-center px-2">
            {{ __('No value needed') }}
        </span>

        <Button
            variant="subtle"
            size="sm"
            icon="trash"
            :aria-label="__('Remove')"
            @click="$emit('remove')"
        />
    </div>
</template>
