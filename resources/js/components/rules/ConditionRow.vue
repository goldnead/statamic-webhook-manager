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

/**
 * The operators, as handles only.
 *
 * Each entry used to carry an English `label` — `'equals'`, `'is set'`,
 * `'matches regex'` — that was then passed to `__()`. Two things were wrong
 * with that, and a comment right here claimed the opposite ("Labels run
 * through __() so they stay translatable"):
 *
 *   - The key was GLOBAL. Any installed addon could redefine `contains`.
 *   - No addon could define it either, because a global key is nobody's to
 *     claim. `__()` hands back what it cannot resolve, so the English source
 *     string reached the screen as its own translation and looked deliberate.
 *
 * The key is now derived from the handle the condition actually stores, which
 * is what makes the wording testable: `NoGlobalTranslationKeysTest` reads this
 * list and requires an entry for every handle in both languages. That is the
 * only kind of check that reaches a `__()` whose argument is a variable — a
 * scanner sees the call, never the value.
 */
const OPS = ['equals', 'not_equals', 'in', 'not_in', 'contains', 'exists',
    'empty', 'gt', 'gte', 'lt', 'lte', 'regex'];

// Statamic's <Select> wraps <Combobox>, which expects `:options` as an array
// of { value, label } objects.
const opOptions = computed(() => OPS.map((op) => ({
    value: op,
    label: __(`webhook-manager::messages.cp.condition_ops.${op}`),
})));

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
            :placeholder="isList ? __('webhook-manager::messages.cp.placeholder_csv') : __('webhook-manager::messages.cp.placeholder_value')"
        />
        <span v-else class="text-xs text-gray-500 dark:text-gray-400 italic w-32 self-center px-2">
            {{ __('webhook-manager::messages.cp.rules_no_value_needed') }}
        </span>

        <Button
            variant="subtle"
            size="sm"
            icon="trash"
            :aria-label="__('webhook-manager::messages.cp.row_remove')"
            @click="$emit('remove')"
        />
    </div>
</template>
