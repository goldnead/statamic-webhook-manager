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
        class="rounded-lg border p-3 space-y-2"
        :class="isRoot
            ? 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40'
            : 'border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800'"
    >
        <!-- Group header: AND/OR toggle + remove (non-root) -->
        <div class="flex items-center justify-between">
            <div class="inline-flex gap-1">
                <Button
                    size="sm"
                    :variant="modelValue.logic === 'and' ? 'primary' : 'default'"
                    :text="__('AND')"
                    @click="setLogic('and')"
                />
                <Button
                    size="sm"
                    :variant="modelValue.logic === 'or' ? 'primary' : 'default'"
                    :text="__('OR')"
                    @click="setLogic('or')"
                />
            </div>
            <Button
                v-if="!isRoot"
                variant="subtle"
                size="sm"
                icon="trash"
                :text="__('Remove group')"
                @click="$emit('remove')"
            />
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
            <Button variant="default" size="sm" icon="plus" :text="__('Condition')" @click="addCondition" />
            <Button variant="default" size="sm" icon="plus" :text="__('Group')" @click="addGroup" />
        </div>
    </div>
</template>
