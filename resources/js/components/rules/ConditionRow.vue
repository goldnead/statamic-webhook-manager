<script setup>
import { computed } from 'vue';
import { Button, Input } from '@statamic/cms/ui';

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

        <select
            class="input-text w-44"
            :value="modelValue.op"
            @change="update({ op: $event.target.value })"
        >
            <option v-for="op in OPS" :key="op.value" :value="op.value">{{ op.label }}</option>
        </select>

        <Input
            v-if="showsValue"
            type="text"
            class="flex-1"
            :model-value="valueInput"
            @update:model-value="valueInput = $event"
            :placeholder="isList ? 'comma, separated, values' : 'value'"
        />
        <span v-else class="text-xs text-gray-500 italic w-32 self-center px-2">
            (no value needed)
        </span>

        <Button variant="default" size="sm" @click="$emit('remove')" :title="__('Remove')">×</Button>
    </div>
</template>
