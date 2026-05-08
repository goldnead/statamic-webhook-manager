<script setup>
import { ref, computed } from 'vue';
import { Head } from '@statamic/cms/inertia';
import { useForm, router } from '@inertiajs/vue3';
import { Header, Button, Panel, Field, Input, Badge, Alert, ConfirmationModal } from '@statamic/cms/ui';
import ConditionGroup from '../../components/rules/ConditionGroup.vue';

const props = defineProps({
    rule: { type: Object, required: true },
    triggerOptions: { type: Object, required: true },
    actionOptions: { type: Object, required: true },
    isNew: { type: Boolean, default: false },
    saveUrl: { type: String, required: true },
    deleteUrl: { type: String, default: null },
    toggleUrl: { type: String, default: null },
    testUrl: { type: String, default: null },
    indexUrl: { type: String, required: true },
});

function jsonOrNull(value) {
    if (value === null || value === undefined || value === '') return null;
    try { return JSON.parse(value); }
    catch (e) { return undefined; } // signal invalid JSON
}

function jsonOrEmpty(value) {
    if (!value) return '';
    try { return JSON.stringify(value, null, 2); }
    catch (e) { return ''; }
}

const form = useForm({
    name: props.rule.name ?? '',
    handle: props.rule.handle ?? '',
    enabled: props.rule.enabled ?? true,
    trigger_type: props.rule.trigger_type ?? Object.keys(props.triggerOptions)[0] ?? '',
    trigger_config: props.rule.trigger_config ?? null,
    conditions: props.rule.conditions ?? null,
    actions: props.rule.actions ?? [],
    stop_on_failure: props.rule.stop_on_failure ?? false,
    order_index: props.rule.order_index ?? 0,
});

// Editor state. Conditions have two editor modes:
//   - 'builder' (default): the ConditionGroup component drives a tree.
//   - 'json':    raw JSON textarea for power users who want to paste a
//                tree from another rule or hand-edit beyond what the
//                builder offers.
// Actions stay JSON-only — a per-action form generator is a v2 candidate.
const conditionMode = ref('builder');
const conditionTree = ref(normaliseTree(form.conditions));
const conditionsJson = ref(jsonOrEmpty(form.conditions));
const conditionsError = ref('');

const actionsJson = ref(jsonOrEmpty(form.actions));
const actionsError = ref('');

function normaliseTree(value) {
    // The builder always works on a root group node. If an existing
    // rule has a single leaf at the top, wrap it; if it's missing,
    // start with an empty AND group.
    if (!value) {
        return { logic: 'and', conditions: [] };
    }
    if (Array.isArray(value.conditions)) {
        return { logic: value.logic ?? 'and', conditions: value.conditions };
    }
    // Top-level leaf — wrap it
    return { logic: 'and', conditions: [value] };
}

function treeOrNull(tree) {
    // Empty root group means "no conditions" — stored as null so the
    // PHP-side ConditionEvaluator returns true for every trigger.
    if (!tree || !Array.isArray(tree.conditions) || tree.conditions.length === 0) {
        return null;
    }
    return tree;
}

function toggleMode(next) {
    conditionsError.value = '';
    if (next === 'json' && conditionMode.value === 'builder') {
        conditionsJson.value = jsonOrEmpty(treeOrNull(conditionTree.value));
    } else if (next === 'builder' && conditionMode.value === 'json') {
        const parsed = jsonOrNull(conditionsJson.value);
        if (parsed === undefined) {
            conditionsError.value = __('Invalid JSON — fix it before switching back to the builder.');
            return;
        }
        conditionTree.value = normaliseTree(parsed);
    }
    conditionMode.value = next;
}

const showDelete = ref(false);
const testing = ref(false);
const testResult = ref(null);
const samplePayload = ref('{\n  "id": 1,\n  "title": "Sample"\n}');

const pageTitle = computed(() => props.isNew
    ? __('Create rule')
    : props.rule.name);

function syncJsonInputs() {
    conditionsError.value = '';
    actionsError.value = '';

    // Conditions: pull from whichever editor is active.
    if (conditionMode.value === 'builder') {
        form.conditions = treeOrNull(conditionTree.value);
    } else {
        if (conditionsJson.value.trim() === '') {
            form.conditions = null;
        } else {
            const parsed = jsonOrNull(conditionsJson.value);
            if (parsed === undefined) {
                conditionsError.value = __('Invalid JSON in conditions.');
                return false;
            }
            form.conditions = parsed;
        }
    }

    if (actionsJson.value.trim() === '') {
        form.actions = [];
    } else {
        try {
            const parsed = JSON.parse(actionsJson.value);
            if (!Array.isArray(parsed)) {
                actionsError.value = __('Actions must be a JSON array.');
                return false;
            }
            form.actions = parsed;
        } catch (e) { actionsError.value = __('Invalid JSON in actions.'); return false; }
    }
    return true;
}

function save() {
    if (!syncJsonInputs()) return;
    const verb = props.isNew ? 'post' : 'patch';
    form[verb](props.saveUrl, { preserveScroll: true });
}

async function runTest() {
    if (!props.testUrl) return;
    if (!syncJsonInputs()) return;

    testing.value = true;
    testResult.value = null;
    try {
        const payload = samplePayload.value.trim() ? JSON.parse(samplePayload.value) : {};
        const res = await window.axios.post(props.testUrl, { sample_payload: payload });
        testResult.value = res.data;
    } catch (e) {
        testResult.value = {
            ok: false,
            message: e?.response?.data?.message
                ?? e?.message
                ?? __('Test failed.'),
            data: {},
        };
    } finally {
        testing.value = false;
    }
}

function destroy() {
    router.delete(props.deleteUrl, {
        preserveScroll: true,
        onSuccess: () => { showDelete.value = false; },
    });
}
</script>

<template>
    <Head :title="pageTitle" />

    <Header :title="pageTitle" icon="instructions">
        <Badge v-if="!isNew" :color="rule.enabled ? 'green' : 'gray'">
            {{ rule.enabled ? __('Enabled') : __('Disabled') }}
        </Badge>
        <Button variant="primary" :loading="form.processing" @click="save">
            {{ __('Save') }}
        </Button>
    </Header>

    <Alert v-if="form.hasErrors" variant="error" :heading="__('Validation failed')" class="mb-4">
        <ul class="list-disc list-inside text-sm">
            <li v-for="(err, key) in form.errors" :key="key">{{ err }}</li>
        </ul>
    </Alert>

    <Panel :heading="__('Identity')" class="mb-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4">
            <Field :label="__('Name')" id="name" :required="true" :error="form.errors.name">
                <Input id="name" v-model="form.name" />
            </Field>
            <Field :label="__('Handle')" id="handle" :required="true" :error="form.errors.handle">
                <Input id="handle" v-model="form.handle" pattern="[a-z0-9_-]+" />
            </Field>
            <Field :label="__('Status')" id="enabled">
                <label class="flex items-center gap-2">
                    <input type="checkbox" v-model="form.enabled" />
                    <span>{{ __('Enabled') }}</span>
                </label>
            </Field>
            <Field :label="__('Order index')" id="order_index"
                   :instructions="__('Lower values run first within the same trigger.')">
                <Input id="order_index" v-model.number="form.order_index" type="number" min="0" />
            </Field>
        </div>
    </Panel>

    <Panel :heading="__('Trigger')" class="mb-4">
        <Field :label="__('Trigger type')" id="trigger_type" :required="true" class="p-4"
               :error="form.errors.trigger_type"
               :instructions="__('Internal event that this rule listens for.')">
            <select id="trigger_type" v-model="form.trigger_type" class="input-text">
                <option v-for="(label, value) in triggerOptions" :key="value" :value="value">
                    {{ label }} ({{ value }})
                </option>
            </select>
        </Field>
    </Panel>

    <Panel :heading="__('Conditions')" class="mb-4">
        <div class="p-4 space-y-3">
            <!-- Mode toggle -->
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-600">
                    {{ __('Optional. AND/OR groups of leaf conditions. Leave empty to always match.') }}
                </p>
                <div class="inline-flex rounded border border-gray-300 overflow-hidden text-xs">
                    <button type="button"
                            class="px-3 py-1"
                            :class="conditionMode === 'builder'
                                ? 'bg-gray-900 text-white'
                                : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300'"
                            @click="toggleMode('builder')">
                        {{ __('Builder') }}
                    </button>
                    <button type="button"
                            class="px-3 py-1 border-l border-gray-300"
                            :class="conditionMode === 'json'
                                ? 'bg-gray-900 text-white'
                                : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300'"
                            @click="toggleMode('json')">
                        {{ __('JSON') }}
                    </button>
                </div>
            </div>

            <Alert v-if="conditionsError" variant="error" :text="conditionsError" />

            <!-- Visual builder -->
            <ConditionGroup
                v-if="conditionMode === 'builder'"
                v-model="conditionTree"
                :is-root="true"
            />

            <!-- Raw JSON for power users -->
            <Field v-else :label="__('Condition tree (JSON)')" id="conditions_json">
                <textarea id="conditions_json" v-model="conditionsJson" rows="10"
                          class="input-text w-full font-mono text-sm"
                          :placeholder='`{
  &quot;logic&quot;: &quot;and&quot;,
  &quot;conditions&quot;: [
    { &quot;field&quot;: &quot;data.status&quot;, &quot;op&quot;: &quot;equals&quot;, &quot;value&quot;: &quot;approved&quot; }
  ]
}`'></textarea>
            </Field>
        </div>
    </Panel>

    <Panel :heading="__('Actions')" class="mb-4">
        <Field :label="__('Action list (JSON array)')" id="actions_json" class="p-4"
               :error="actionsError"
               :instructions="__('Ordered list of actions to run when the conditions match.')">
            <textarea id="actions_json" v-model="actionsJson" rows="10"
                      class="input-text w-full font-mono text-sm"
                      :placeholder='`[
  { &quot;handle&quot;: &quot;send_outbound_webhook&quot;, &quot;config&quot;: { &quot;webhook_handle&quot;: &quot;notify-crm&quot; } },
  { &quot;handle&quot;: &quot;write_log_note&quot;, &quot;config&quot;: { &quot;message&quot;: &quot;CRM notified.&quot; } }
]`'></textarea>
        </Field>
        <div class="px-4 pb-4 text-xs text-gray-600">
            <strong>{{ __('Available action handles:') }}</strong>
            <ul class="mt-1 list-disc list-inside">
                <li v-for="(label, handle) in actionOptions" :key="handle">
                    <code>{{ handle }}</code> &mdash; {{ label }}
                </li>
            </ul>
        </div>
        <div class="px-4 pb-4">
            <label class="flex items-center gap-2">
                <input type="checkbox" v-model="form.stop_on_failure" />
                <span class="text-sm">{{ __('Stop on first action failure') }}</span>
            </label>
        </div>
    </Panel>

    <Panel v-if="!isNew && testUrl" :heading="__('Test')" class="mb-4">
        <div class="p-4">
            <Field :label="__('Sample payload (JSON)')" id="sample_payload"
                   :instructions="__('Payload that simulates the trigger event. Actions execute for real.')">
                <textarea id="sample_payload" v-model="samplePayload" rows="6"
                          class="input-text w-full font-mono text-sm"></textarea>
            </Field>
            <div class="mt-3">
                <Button variant="default" :loading="testing" @click="runTest">
                    {{ __('Run test') }}
                </Button>
            </div>

            <Alert v-if="testResult" :variant="testResult.ok ? 'success' : 'error'" class="mt-4"
                   :heading="testResult.ok ? __('Test succeeded') : __('Test failed')"
                   :text="testResult.message" />

            <div v-if="testResult && testResult.data" class="mt-3">
                <h4 class="text-xs uppercase font-semibold text-gray-600 mb-1">{{ __('Result detail') }}</h4>
                <pre class="text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-auto">{{ JSON.stringify(testResult.data, null, 2) }}</pre>
            </div>
        </div>
    </Panel>

    <div v-if="!isNew" class="mt-8 flex justify-between">
        <Button variant="danger" @click="showDelete = true">{{ __('Delete') }}</Button>
        <Button variant="primary" :loading="form.processing" @click="save">{{ __('Save') }}</Button>
    </div>

    <ConfirmationModal
        v-if="!isNew"
        :open="showDelete"
        :title="__('Delete rule')"
        :body-text="__('This permanently removes the rule. Past executions stay in the logs.')"
        :button-text="__('Delete')"
        :danger="true"
        @confirm="destroy"
        @update:open="showDelete = $event"
    />
</template>
