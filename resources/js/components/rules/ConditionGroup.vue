<script setup>
import { computed } from 'vue';
import { Button } from '@statamic/cms/ui';
import ConditionRow from './ConditionRow.vue';

/**
 * Recursive group node: { logic: 'and'|'or', conditions: [leaf|group, ...] }.
 *
 * Emits the full updated group back up via `update:modelValue` so the
 * parent can store the whole tree in `form.conditions`. The PHP-side
 * `ConditionEvaluator` accepts this exact shape — no transformation
 * happens between the UI and the engine.
 */

const props = defineProps({
    modelValue: { type: Object, required: true },
    isRoot:     { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue', 'remove']);

const items = computed(() => Array.isArray(props.modelValue.conditions)
    ? props.modelValue.conditions
    : []);

function isGroup(node) {
    return node && Array.isArray(node.conditions);
}

function update(patch) {
    emit('update:modelValue', { ...props.modelValue, ...patch });
}

function setLogic(logic) {
    update({ logic });
}

function addCondition() {
    update({
        conditions: [...items.value, { field: '', op: 'equals', value: '' }],
    });
}

function addGroup() {
    update({
        conditions: [...items.value, { logic: 'and', conditions: [] }],
    });
}

function updateChild(index, next) {
    const list = items.value.slice();
    list[index] = next;
    update({ conditions: list });
}

function removeChild(index) {
    update({ conditions: items.value.filter((_, i) => i !== index) });
}
</script>

<template>
    <div
        class="rounded border p-3 space-y-2"
        :class="isRoot
            ? 'border-gray-200 bg-gray-50 dark:bg-gray-900/40'
            : 'border-gray-300 bg-white dark:bg-gray-800'"
    >
        <!-- Group header: AND/OR toggle + remove (non-root) -->
        <div class="flex items-center justify-between">
            <div class="inline-flex rounded border border-gray-300 overflow-hidden text-xs">
                <button
                    type="button"
                    class="px-3 py-1"
                    :class="modelValue.logic === 'and'
                        ? 'bg-gray-900 text-white'
                        : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300'"
                    @click="setLogic('and')"
                >{{ __('AND') }}</button>
                <button
                    type="button"
                    class="px-3 py-1 border-l border-gray-300"
                    :class="modelValue.logic === 'or'
                        ? 'bg-gray-900 text-white'
                        : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300'"
                    @click="setLogic('or')"
                >{{ __('OR') }}</button>
            </div>
            <Button v-if="!isRoot" variant="default" size="sm" @click="$emit('remove')">
                {{ __('Remove group') }}
            </Button>
        </div>

        <!-- Empty state -->
        <p v-if="!items.length" class="text-xs text-gray-500 italic">
            {{ isRoot
                ? __('No conditions — rule fires for every matching trigger.')
                : __('Empty group — add a condition or another nested group below.') }}
        </p>

        <!-- Children: leaves or nested groups -->
        <div v-for="(child, idx) in items" :key="idx" class="ml-2">
            <ConditionGroup
                v-if="isGroup(child)"
                :model-value="child"
                @update:model-value="updateChild(idx, $event)"
                @remove="removeChild(idx)"
            />
            <ConditionRow
                v-else
                :model-value="child"
                @update:model-value="updateChild(idx, $event)"
                @remove="removeChild(idx)"
            />
        </div>

        <!-- Toolbar -->
        <div class="flex gap-2 pt-1">
            <Button variant="default" size="sm" @click="addCondition">
                + {{ __('Condition') }}
            </Button>
            <Button variant="default" size="sm" @click="addGroup">
                + {{ __('Group') }}
            </Button>
        </div>
    </div>
</template>
