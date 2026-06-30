<script setup>
import { ref, computed, watch } from 'vue';
import { Head } from '@statamic/cms/inertia';
import { useForm, router } from '@inertiajs/vue3';
import {
    Header,
    Button,
    Badge,
    Alert,
    Field,
    Input,
    Textarea,
    Select,
    Switch,
    CodeEditor,
    Tabs,
    TabList,
    TabTrigger,
    TabContent,
    Panel,
    ConfirmationModal,
    StatusIndicator,
    CommandPaletteItem,
} from '@statamic/cms/ui';
import ConditionGroup from '../../components/rules/ConditionGroup.vue';

/**
 * Rule edit/create page.
 *
 * Tabs: General | Trigger | Conditions | Actions | Settings | Test
 *
 * The existing ConditionGroup component is retained as-is and mounted
 * into the Conditions tab. It operates on a normalised tree structure
 * (`{ logic, conditions }`) which we serialise back to form.conditions
 * on save. Builder/JSON-Toggle from the old Edit.vue is preserved via
 * a lightweight "Show JSON" Switch — the toggle no longer controls
 * navigation (tabs do that now) but remains available for power users.
 *
 * Actions stay JSON-only in v1. A per-action form builder is a v2 candidate.
 */
const props = defineProps({
    rule: { type: Object, required: true },
    triggerOptions: { type: Object, required: true },
    actionOptions: { type: Object, required: true },
    isNew: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
    canTest: { type: Boolean, default: false },
    saveUrl: { type: String, required: true },
    deleteUrl: { type: String, default: null },
    toggleUrl: { type: String, default: null },
    testUrl: { type: String, default: null },
    indexUrl: { type: String, required: true },
});

// ─── helpers ──────────────────────────────────────────────────────────────────

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

function normaliseTree(value) {
    // The ConditionGroup always works on a root group node. If an existing
    // rule has a single leaf at the top, wrap it; if missing, start with
    // an empty AND group.
    if (!value) return { logic: 'and', conditions: [] };
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

// ─── form ─────────────────────────────────────────────────────────────────────

const form = useForm({
    name: props.rule.name ?? '',
    handle: props.rule.handle ?? '',
    enabled: props.rule.enabled ?? true,
    description: props.rule.description ?? '',
    trigger_type: props.rule.trigger_type ?? Object.keys(props.triggerOptions)[0] ?? '',
    trigger_config: props.rule.trigger_config ?? null,
    conditions: props.rule.conditions ?? null,
    actions: props.rule.actions ?? [],
    stop_on_failure: props.rule.stop_on_failure ?? false,
    order_index: props.rule.order_index ?? 0,
});

// ─── JSON CodeEditor placeholders ────────────────────────────────────────────
//
// Defined as JS template literals because escaped double-quotes inside a
// double-quoted Vue attribute (`:placeholder="'{\"key\": …}'"`) trip the
// SFC parser ("Unterminated string constant").

const triggerConfigPlaceholder = `{
  "collection": "articles"
}`;

const conditionsPlaceholder = `{
  "logic": "and",
  "conditions": []
}`;

const actionsPlaceholder = `[
  {
    "handle": "send_outbound",
    "webhook": "my-webhook"
  }
]`;

// ─── conditions editor state ──────────────────────────────────────────────────

// Two editing modes:
// - 'builder' (default): ConditionGroup drives a tree in conditionTree.
// - 'json': raw CodeEditor for power users or copy-paste from another rule.
// A Switch on the Conditions tab toggles between them without affecting
// navigation. The two representations are kept in sync on switch.
const conditionMode = ref('builder');
const showConditionJson = ref(false);
const conditionTree = ref(normaliseTree(form.conditions));
const conditionsJson = ref(jsonOrEmpty(form.conditions));
const conditionsError = ref('');

watch(showConditionJson, show => {
    conditionsError.value = '';
    if (show) {
        // Switching to JSON: serialise the current tree.
        conditionsJson.value = jsonOrEmpty(treeOrNull(conditionTree.value));
    } else {
        // Switching back to builder: parse the JSON.
        const parsed = jsonOrNull(conditionsJson.value);
        if (parsed === undefined) {
            conditionsError.value = __('Invalid JSON — fix it before switching back to the builder.');
            // Revert the switch state without triggering the watcher again.
            showConditionJson.value = true;
            return;
        }
        conditionTree.value = normaliseTree(parsed);
    }
});

// ─── actions editor state ─────────────────────────────────────────────────────

const actionsJson = ref(jsonOrEmpty(form.actions));
const actionsError = ref('');

// ─── test tab state ───────────────────────────────────────────────────────────

const testing = ref(false);
const testResult = ref(null);
const samplePayload = ref('{\n  "id": 1,\n  "title": "Sample"\n}');

// ─── tab / page state ─────────────────────────────────────────────────────────

const activeTab = ref('general');
const showDelete = ref(false);

// Statamic's <Select> wraps <Combobox>, which expects `:options` as an
// Array of { value, label } objects — not nested HTML <option> tags.
function objectToOptions(obj) {
    if (!obj || typeof obj !== 'object') return [];
    return Object.entries(obj).map(([value, label]) => ({ value, label }));
}

const triggerOptionsArray = computed(() => objectToOptions(props.triggerOptions));

const pageTitle = computed(() =>
    props.isNew ? __('Create rule') : (props.rule.name || __('Rule'))
);
const saveLabel = computed(() => props.isNew ? __('Create') : __('Save'));

// Surface server-side validation errors on the right tab so users don't
// miss them when they are on a different tab.
const tabsWithErrors = computed(() => {
    const map = {
        general:    ['name', 'handle', 'enabled', 'order_index'],
        trigger:    ['trigger_type', 'trigger_config'],
        conditions: ['conditions'],
        actions:    ['actions'],
        settings:   ['stop_on_failure'],
    };
    const tabs = new Set();
    for (const [tab, keys] of Object.entries(map)) {
        if (keys.some(k => form.errors[k])) tabs.add(tab);
    }
    return tabs;
});

watch(() => form.hasErrors, hasErrors => {
    if (!hasErrors) return;
    const firstTabWithError = ['general', 'trigger', 'conditions', 'actions', 'settings']
        .find(t => tabsWithErrors.value.has(t));
    if (firstTabWithError) activeTab.value = firstTabWithError;
});

// ─── save / submit ────────────────────────────────────────────────────────────

function syncFormFields() {
    conditionsError.value = '';
    actionsError.value = '';

    // Conditions: pull from whichever editor is currently visible.
    if (!showConditionJson.value) {
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

    // Actions: always JSON.
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
        } catch (e) {
            actionsError.value = __('Invalid JSON in actions.');
            return false;
        }
    }

    return true;
}

function save() {
    if (!syncFormFields()) return;
    if (!props.saveUrl) {
        console.error(
            '[webhook-manager] Rules/Edit: saveUrl prop is missing — cannot submit.',
            'Inertia props received:', { isNew: props.isNew, saveUrl: props.saveUrl, indexUrl: props.indexUrl }
        );
        return;
    }
    const verb = props.isNew ? 'post' : 'patch';
    form.submit(verb, props.saveUrl, { preserveScroll: true });
}

function destroy() {
    if (!props.deleteUrl) {
        console.error('[webhook-manager] Rules/Edit: deleteUrl prop is missing — cannot delete.');
        return;
    }
    router.delete(props.deleteUrl, {
        preserveScroll: true,
        onSuccess: () => { showDelete.value = false; },
    });
}

// ─── test ─────────────────────────────────────────────────────────────────────

async function runTest() {
    if (!props.testUrl) return;
    if (!syncFormFields()) return;

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
</script>

<template>
    <Head :title="[pageTitle, __('Rules'), __('Webhook Manager')]" />

    <div class="max-w-5xl 3xl:max-w-6xl mx-auto" data-max-width-wrapper>
        <Header :title="pageTitle" icon="filter">
            <template v-if="!isNew" #subtitle>
                <StatusIndicator :status="rule.enabled ? 'published' : 'draft'" />
                <Badge
                    :color="rule.enabled ? 'green' : 'gray'"
                    :text="rule.enabled ? __('Active') : __('Disabled')"
                />
            </template>

            <Button
                v-if="!isNew && canTest && testUrl"
                :loading="testing"
                :text="__('Test')"
                icon="arrow-up-right"
                @click="() => { activeTab = 'test'; }"
            />
            <Button
                variant="primary"
                :loading="form.processing"
                :text="saveLabel"
                @click="save"
            />

            <CommandPaletteItem
                v-if="!isNew && canDelete && deleteUrl"
                category="Actions"
                :text="__('Delete rule')"
                icon="trash"
                :action="() => (showDelete = true)"
            />
        </Header>

        <Tabs v-model="activeTab" class="mt-4">
            <TabList>
                <TabTrigger value="general">
                    {{ __('General') }}
                    <Badge v-if="tabsWithErrors.has('general')" color="red" class="ms-1.5" :text="__('!')" />
                </TabTrigger>
                <TabTrigger value="trigger">
                    {{ __('Trigger') }}
                    <Badge v-if="tabsWithErrors.has('trigger')" color="red" class="ms-1.5" :text="__('!')" />
                </TabTrigger>
                <TabTrigger value="conditions">
                    {{ __('Conditions') }}
                    <Badge v-if="tabsWithErrors.has('conditions')" color="red" class="ms-1.5" :text="__('!')" />
                </TabTrigger>
                <TabTrigger value="actions">
                    {{ __('Actions') }}
                    <Badge v-if="tabsWithErrors.has('actions')" color="red" class="ms-1.5" :text="__('!')" />
                </TabTrigger>
                <TabTrigger value="settings">
                    {{ __('Settings') }}
                    <Badge v-if="tabsWithErrors.has('settings')" color="red" class="ms-1.5" :text="__('!')" />
                </TabTrigger>
                <TabTrigger v-if="!isNew && testUrl" value="test">
                    {{ __('Test') }}
                </TabTrigger>
            </TabList>

            <!-- ───────── General ───────── -->
            <TabContent value="general">
                <Panel class="mt-4">
                    <div class="bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-gray-700/80 shadow-sm p-6 space-y-6">
                        <Field inline
                            :label="__('Name')"
                            id="name"
                            :required="true"
                            :error="form.errors.name"
                            :instructions="__('Human-readable name shown across the CP.')"
                        >
                            <Input id="name" v-model="form.name" autofocus />
                        </Field>

                        <Field inline
                            :label="__('Handle')"
                            id="handle"
                            :required="true"
                            :error="form.errors.handle"
                            :instructions="__('Internal identifier. Lowercase, hyphens or underscores only.')"
                        >
                            <Input id="handle" v-model="form.handle" pattern="[a-z0-9_-]+" />
                        </Field>

                        <Field inline
                            :label="__('Description')"
                            id="description"
                            class="md:col-span-2"
                            :error="form.errors.description"
                        >
                            <Textarea id="description" v-model="form.description" :rows="2" />
                        </Field>

                        <Field inline
                            :label="__('Order')"
                            id="order_index"
                            :error="form.errors.order_index"
                            :instructions="__('Lower numbers run first. Rules with equal order are sorted by name.')"
                        >
                            <Input id="order_index" v-model.number="form.order_index" type="number" min="0" />
                        </Field>

                        <Field inline
                            :label="__('Status')"
                            id="enabled"
                            :error="form.errors.enabled"
                        >
                            <Switch
                                id="enabled"
                                v-model="form.enabled"
                                :text="form.enabled ? __('Enabled') : __('Disabled')"
                            />
                        </Field>
                    </div>
                </Panel>
            </TabContent>

            <!-- ───────── Trigger ───────── -->
            <TabContent value="trigger">
                <Panel class="mt-4">
                    <div class="bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-gray-700/80 shadow-sm p-6 space-y-6">
                        <Field inline
                            :label="__('Trigger type')"
                            id="trigger_type"
                            :required="true"
                            :error="form.errors.trigger_type"
                            :instructions="__('The internal event that fires this rule.')"
                        >
                            <Select id="trigger_type" v-model="form.trigger_type" :options="triggerOptionsArray" />
                        </Field>

                        <Field inline
                            :label="__('Trigger config (JSON)')"
                            id="trigger_config"
                            :error="form.errors.trigger_config"
                            :instructions="__('Optional. Trigger-specific filter parameters — see the trigger\'s documentation for available keys.')"
                        >
                            <CodeEditor
                                id="trigger_config"
                                v-model="form.trigger_config"
                                mode="json"
                                :rows="6"
                                :placeholder="triggerConfigPlaceholder"
                            />
                        </Field>
                    </div>
                </Panel>
            </TabContent>

            <!-- ───────── Conditions ───────── -->
            <TabContent value="conditions">
                <Panel class="mt-4">
                    <div class="p-6 space-y-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ __('Optional. AND/OR groups of leaf conditions. Leave empty to always match.') }}
                            </p>
                            <Field inline :label="__('Show JSON')" class="shrink-0 ms-4">
                                <Switch v-model="showConditionJson" />
                            </Field>
                        </div>

                        <Alert
                            v-if="conditionsError"
                            variant="error"
                            :text="conditionsError"
                            class="mb-2"
                        />

                        <!-- Visual builder (ConditionGroup) — default mode -->
                        <ConditionGroup
                            v-if="!showConditionJson"
                            v-model="conditionTree"
                        />

                        <!-- JSON editor — debug/power-user mode (read/write) -->
                        <div v-else>
                            <CodeEditor
                                v-model="conditionsJson"
                                mode="json"
                                :rows="16"
                                :placeholder="conditionsPlaceholder"
                            />
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                {{ __('Editing JSON directly. Switch back to builder to use the visual editor.') }}
                            </p>
                        </div>
                    </div>
                </Panel>
            </TabContent>

            <!-- ───────── Actions ───────── -->
            <TabContent value="actions">
                <Panel class="mt-4">
                    <div class="p-6 space-y-4">
                        <Alert
                            variant="info"
                            :heading="__('JSON format')"
                            :text="__('Actions are an ordered array. Each object requires at minimum a handle key. Available handles are listed below.')"
                        />

                        <Alert
                            v-if="actionsError"
                            variant="error"
                            :text="actionsError"
                        />

                        <Field inline
                            :label="__('Actions (JSON array)')"
                            id="actions"
                            :error="form.errors.actions"
                            :instructions="__('Each action runs in order. Stop on failure can be configured in the Settings tab.')"
                        >
                            <CodeEditor
                                id="actions"
                                v-model="actionsJson"
                                mode="json"
                                :rows="14"
                                :placeholder="actionsPlaceholder"
                            />
                        </Field>

                        <div v-if="Object.keys(actionOptions).length" class="mt-2">
                            <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ __('Available action handles:') }}
                            </p>
                            <ul class="space-y-0.5">
                                <li
                                    v-for="(label, handle) in actionOptions"
                                    :key="handle"
                                    class="text-xs font-mono text-gray-600 dark:text-gray-400"
                                >
                                    {{ handle }} <span class="font-sans text-gray-500">— {{ label }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </Panel>
            </TabContent>

            <!-- ───────── Settings ───────── -->
            <TabContent value="settings">
                <Panel class="mt-4">
                    <div class="bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-gray-700/80 shadow-sm p-6 space-y-6">
                        <Field inline
                            :label="__('Stop on first failure')"
                            id="stop_on_failure"
                            class="md:col-span-2"
                            :error="form.errors.stop_on_failure"
                            :instructions="__('When enabled, if any action returns an error the remaining actions in this rule are skipped.')"
                        >
                            <Switch
                                id="stop_on_failure"
                                v-model="form.stop_on_failure"
                                :text="__('Stop on first action failure')"
                            />
                        </Field>
                    </div>
                </Panel>
            </TabContent>

            <!-- ───────── Test ───────── -->
            <TabContent v-if="!isNew && testUrl" value="test">
                <Panel class="mt-4">
                    <div class="p-6 space-y-6">
                        <Field inline
                            :label="__('Sample payload (JSON)')"
                            id="sample_payload"
                            :instructions="__('This payload is passed to the rule engine as if it were a real trigger event.')"
                        >
                            <CodeEditor
                                id="sample_payload"
                                v-model="samplePayload"
                                mode="json"
                                :rows="10"
                            />
                        </Field>

                        <div class="flex justify-end">
                            <Button
                                variant="primary"
                                :loading="testing"
                                :text="__('Run test')"
                                icon="arrow-up-right"
                                @click="runTest"
                            />
                        </div>

                        <div v-if="testResult" class="space-y-3">
                            <Alert
                                :variant="testResult.ok ? 'success' : 'error'"
                                :heading="testResult.ok ? __('Rule matched — actions executed') : __('Rule did not match or an error occurred')"
                                :text="testResult.message ?? ''"
                            />

                            <Field inline v-if="testResult.data && Object.keys(testResult.data).length" :label="__('Result detail')">
                                <CodeEditor
                                    :model-value="JSON.stringify(testResult.data, null, 2)"
                                    mode="json"
                                    :read-only="true"
                                    :rows="10"
                                />
                            </Field>
                        </div>
                    </div>
                </Panel>
            </TabContent>
        </Tabs>

        <div v-if="!isNew && canDelete" class="mt-8 flex justify-between items-center">
            <Button variant="danger" :text="__('Delete rule')" @click="showDelete = true" />
            <Button
                variant="primary"
                :loading="form.processing"
                :text="saveLabel"
                @click="save"
            />
        </div>

        <ConfirmationModal
            v-if="!isNew && deleteUrl"
            :open="showDelete"
            :title="__('Delete rule')"
            :body-text="__('This permanently removes the rule configuration.')"
            :button-text="__('Delete')"
            :danger="true"
            @confirm="destroy"
            @update:open="showDelete = $event"
        />
    </div>
</template>
