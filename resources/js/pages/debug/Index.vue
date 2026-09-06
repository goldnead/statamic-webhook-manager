<script setup>
/**
 * Debug — what this installation actually resolved to.
 *
 * Three panels at the bottom came over from the settings screen when the
 * settings themselves moved to the suite's shared one. None of them is a
 * setting: `environment` is deployment-owned (env vars, and the inbound prefix
 * that `route:cache` freezes), the raw config tree is a printout with its
 * secrets masked, and the storage driver is a detection plus an action —
 * switching it moves the stored webhook configuration before it flips.
 *
 * Two abilities reach this page. A user with only `manage webhook settings`
 * gets the three panels below and no debug tools; the server withholds
 * `previewUrl`/`simulateUrl` from them, which is what hides those panels here.
 */
import axios from 'axios';
import { ref, computed } from 'vue';
import { Head, useForm } from '@statamic/cms/inertia';
import {
    Header,
    Card,
    Panel,
    Listing,
    Field,
    Input,
    Select,
    CodeEditor,
    Button,
    Alert,
    Badge,
    Icon,
    ConfirmationModal,
} from '@statamic/cms/ui';

const props = defineProps({
    triggers:    { type: Array,  default: () => [] },
    resolvers:   { type: Array,  default: () => [] },
    /** Null when the viewer lacks `use webhook debug tools`. */
    previewUrl:  { type: String, default: null },
    simulateUrl: { type: String, default: null },

    /** Whether the viewer may see and switch the storage driver. */
    canManageSettings: { type: Boolean, default: false },
    /** Deployment-owned settings, shown read-only */
    environment:    { type: Array,  default: () => [] },
    /** JSON-encoded resolved config tree, secrets masked */
    rawConfig:      { type: String, default: '{}' },
    /** Absolute path to the config file on disk */
    configFilePath: { type: String, default: '' },
    /** Where the editable settings went */
    settingsUrl:    { type: String, default: '' },
    /** Active storage driver + record counts + switch URL; null without the permission */
    storage:        { type: Object, default: null },
});

// ── Trigger Inspector columns ──────────────────────────────────────────────
const inspectorColumns = [
    { field: 'handle',      label: __('webhook-manager::messages.cp.col_handle'),      sortable: false },
    { field: 'label',       label: __('webhook-manager::messages.cp.col_label'),        sortable: false },
    { field: 'source_type', label: __('webhook-manager::messages.cp.col_source_type'),  sortable: false },
];

// ── Template Preview state ─────────────────────────────────────────────────
const template = ref(JSON.stringify({ title: '{{ entry:title }}', site: '{{ site:handle }}' }, null, 2));
const samplePayload = ref(JSON.stringify({ id: '1', title: 'Hello', site: 'default' }, null, 2));
const sourceType = ref('entry');

const sourceTypeOptions = [
    { value: 'entry',           label: __('webhook-manager::messages.cp.source_entry') },
    { value: 'form_submission', label: __('webhook-manager::messages.cp.source_form_submission') },
    { value: 'user',            label: __('webhook-manager::messages.cp.source_user') },
    { value: 'asset',           label: __('webhook-manager::messages.cp.source_asset') },
];

const previewing = ref(false);
const previewResult = ref(null);

async function runPreview() {
    let payload;
    try {
        payload = JSON.parse(samplePayload.value);
    } catch (e) {
        previewResult.value = { rendered: '', issues: [__('webhook-manager::messages.cp.invalid_sample_json', { error: e.message })] };
        return;
    }
    previewing.value = true;
    previewResult.value = null;
    try {
        const res = await axios.post(props.previewUrl, {
            template:       template.value,
            sample_payload: payload,
            source_type:    sourceType.value,
        });
        previewResult.value = res.data;
    } catch (e) {
        previewResult.value = {
            rendered: '',
            issues:   [e?.response?.data?.message ?? e.message],
        };
    } finally {
        previewing.value = false;
    }
}

const previewHasErrors = computed(() =>
    previewResult.value && previewResult.value.issues?.length > 0
);
const previewVariant = computed(() =>
    previewHasErrors.value ? 'error' : 'success'
);
const previewMessage = computed(() =>
    previewHasErrors.value
        ? previewResult.value.issues.join(' · ')
        : __('webhook-manager::messages.cp.debug_render_ok')
);

// ── Simulate Trigger state ─────────────────────────────────────────────────
const selectedTrigger = ref(props.triggers[0]?.handle ?? '');
const triggerPayload = ref(JSON.stringify({ id: '1', title: 'Hello', site: 'default' }, null, 2));

const triggerOptions = computed(() =>
    props.triggers.map(t => ({ value: t.handle, label: t.label || t.handle }))
);

const simulating = ref(false);
const simulateResult = ref(null);

async function runSimulate() {
    if (!props.simulateUrl) return;
    let payload;
    try {
        payload = JSON.parse(triggerPayload.value);
    } catch (e) {
        simulateResult.value = { success: false, message: __('webhook-manager::messages.cp.invalid_json', { error: e.message }), response: null };
        return;
    }
    simulating.value = true;
    simulateResult.value = null;
    try {
        const res = await axios.post(props.simulateUrl, {
            trigger:        selectedTrigger.value,
            sample_payload: payload,
        });
        simulateResult.value = { success: true, message: __('webhook-manager::messages.cp.debug_simulated'), response: res.data };
    } catch (e) {
        simulateResult.value = {
            success:  false,
            message:  e?.response?.data?.message ?? e.message,
            response: e?.response?.data ?? null,
        };
    } finally {
        simulating.value = false;
    }
}

const simulateResponseJson = computed(() =>
    simulateResult.value?.response
        ? JSON.stringify(simulateResult.value.response, null, 2)
        : ''
);

// ── Resolver Inspector ─────────────────────────────────────────────────────
const resolverColumns = [
    { field: 'namespace', label: __('webhook-manager::messages.cp.col_namespace'), sortable: false },
];

/**
 * Render a resolver namespace as the literal token a user would type
 * in a template, e.g. `{{ entry:key }}`.
 *
 * Computed as a JS string so the Vue template parser doesn't treat the
 * inner `{{` as a mustache delimiter.
 */
function namespaceExample(value) {
    return '{{ ' + value + ':key }}';
}

// ── Config file path ───────────────────────────────────────────────────────
const copied = ref(false);

function copyPath() {
    navigator.clipboard.writeText(props.configFilePath).then(() => {
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    });
}

// ── Storage driver switch ──────────────────────────────────────────────────
const showSwitch = ref(false);

// Target driver to switch to (the other store). Uses form.submit('post', url)
// — the proven submission helper in this Inertia build (the dynamic-verb
// form.post()/router.post() helpers misbehave here, same as the edit forms).
const switchForm = useForm({ driver: props.storage?.target ?? null });

const storageCountsLine = computed(() => {
    const c = props.storage?.counts || {};
    return __('webhook-manager::messages.storage_counts_line', {
        outbound: c['outbound webhooks'] ?? 0,
        inbound: c['inbound endpoints'] ?? 0,
        rules: c['rules'] ?? 0,
        templates: c['templates'] ?? 0,
    });
});

function switchStorage() {
    switchForm.submit('post', props.storage.switch_url, {
        preserveScroll: true,
        onFinish: () => { showSwitch.value = false; },
    });
}

// The driver switch can be refused too, and its rejection has no field to
// appear in.
const switchErrors = computed(() => switchForm.errors ?? {});
</script>

<template>
    <Head :title="[__('webhook-manager::messages.cp.page_debug'), __('webhook-manager::messages.cp.app_name')]" />

    <div class="max-w-page mx-auto">

        <Header :title="__('webhook-manager::messages.cp.page_debug')" icon="code-block" />

        <!-- ── Trigger Inspector ───────────────────────────────────────── -->
        <Panel
            :heading="__('webhook-manager::messages.cp.debug_triggers_heading')"
            :subheading="__('webhook-manager::messages.cp.debug_triggers_sub')"
        >
            <Listing
                :items="triggers"
                :columns="inspectorColumns"
                :allow-bulk-actions="false"
                :allow-search="true"
            >
                <template #cell-handle="{ value }">
                    <code class="font-mono text-sm">{{ value }}</code>
                </template>
                <template #cell-source_type="{ value }">
                    <Badge :text="value" color="default" />
                </template>
            </Listing>
        </Panel>

        <!-- ── Resolver Inspector ─────────────────────────────────────── -->
        <Panel
            v-if="resolvers.length > 0"
            :heading="__('webhook-manager::messages.cp.debug_resolvers_heading')"
            :subheading="__('webhook-manager::messages.cp.debug_resolvers_sub')"
        >
            <Listing
                :items="resolvers"
                :columns="resolverColumns"
                :allow-bulk-actions="false"
                :allow-search="false"
            >
                <template #cell-namespace="{ value }">
                    <code class="font-mono text-sm" v-text="namespaceExample(value)" />
                </template>
            </Listing>
        </Panel>

        <!-- ── Template Preview ─────────────────────────────────────────
             `previewUrl` is null for a viewer who reaches this page on
             `manage webhook settings` alone; the action behind the button
             refuses them anyway, so the panel is not offered. -->
        <Panel
            v-if="previewUrl"
            :heading="__('webhook-manager::messages.cp.debug_preview_heading')"
            :subheading="__('webhook-manager::messages.cp.debug_preview_sub')"
        >
            <Card>
                <div class="space-y-4">

                    <Field :label="__('webhook-manager::messages.cp.debug_template_label')" :instructions="__('webhook-manager::messages.cp.debug_template_hint')">
                        <CodeEditor v-model="template" mode="json" />
                    </Field>

                    <Field :label="__('webhook-manager::messages.cp.col_source_type')">
                        <Select v-model="sourceType" :options="sourceTypeOptions" />
                    </Field>

                    <Field :label="__('webhook-manager::messages.cp.debug_sample_payload')" :instructions="__('webhook-manager::messages.cp.debug_sample_payload_hint')">
                        <CodeEditor v-model="samplePayload" mode="json" />
                    </Field>

                    <div>
                        <Button
                            :text="previewing ? __('webhook-manager::messages.cp.btn_rendering') : __('webhook-manager::messages.cp.btn_preview')"
                            variant="primary"
                            :disabled="previewing"
                            @click="runPreview"
                        />
                </div>

                <template v-if="previewResult">
                    <!-- `text`, not `message`: Alert reads text/heading/
                         variant/icon and a default slot. `message` landed in
                         $attrs and the banner rendered empty — a preview that
                         failed and a preview that succeeded looked the same,
                         both a blank strip. -->
                    <Alert :variant="previewVariant" :text="previewMessage" />
                    <Field
                        v-if="previewResult.rendered"
                        :label="__('webhook-manager::messages.cp.templates_rendered_output')"
                    >
                        <CodeEditor :model-value="previewResult.rendered" read-only />
                    </Field>
                </template>

                </div>
            </Card>
        </Panel>

        <!-- ── Simulate Trigger ───────────────────────────────────────── -->
        <Panel
            v-if="simulateUrl && triggers.length > 0"
            :heading="__('webhook-manager::messages.cp.debug_simulate_heading')"
            :subheading="__('webhook-manager::messages.cp.debug_simulate_sub')"
        >
            <Card>
                <div class="space-y-4">

                    <Field :label="__('webhook-manager::messages.cp.col_trigger')">
                        <Select v-model="selectedTrigger" :options="triggerOptions" />
                    </Field>

                    <Field :label="__('webhook-manager::messages.cp.debug_sample_payload')" :instructions="__('webhook-manager::messages.cp.debug_simulate_hint')">
                        <CodeEditor v-model="triggerPayload" mode="json" />
                    </Field>

                    <div>
                        <Button
                            :text="simulating ? __('webhook-manager::messages.cp.btn_running') : __('webhook-manager::messages.cp.btn_run')"
                            variant="primary"
                            :disabled="simulating"
                            @click="runSimulate"
                        />
                </div>

                <template v-if="simulateResult">
                    <!-- Same here: `text` is the prop, `message` is not. -->
                    <Alert
                        :variant="simulateResult.success ? 'success' : 'error'"
                        :text="simulateResult.message"
                    />
                    <Field
                        v-if="simulateResponseJson"
                        :label="__('webhook-manager::messages.cp.tab_response')"
                    >
                        <CodeEditor :model-value="simulateResponseJson" mode="json" read-only />
                    </Field>
                </template>

                </div>
            </Card>
        </Panel>

        <!-- ── What the server said when the driver switch was refused ─ -->
        <Alert
            v-if="Object.keys(switchErrors).length"
            variant="error"
            class="mb-6"
            data-webhook-form-errors
        >
            <ul class="list-disc list-inside space-y-0.5">
                <li v-for="(err, key) in switchErrors" :key="key">{{ err }}</li>
            </ul>
        </Alert>

        <!-- ── Deployment-owned, shown but not editable ─────────────────
             Came from the settings screen. These resolve from env() or are
             frozen by route:cache; a database row that outranks either is a
             setting that changes back on the next deploy. -->
        <Panel :heading="__('webhook-manager::settings.environment.heading')">
            <Card>
                <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('webhook-manager::settings.environment.description') }}
                </p>

                <div
                    v-for="entry in environment"
                    :key="entry.env + entry.label"
                    class="flex items-start justify-between gap-4 border-t border-gray-200 py-3 first:border-t-0 dark:border-gray-800"
                    :data-settings-environment="entry.env"
                >
                    <div>
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ entry.label }}</span>
                        <span class="block font-mono text-xs text-gray-500 dark:text-gray-400">{{ entry.env }}</span>
                    </div>
                    <span class="text-sm text-right text-gray-900 dark:text-gray-100">{{ entry.value }}</span>
                </div>
            </Card>
        </Panel>

        <!-- ── Storage driver (an action, not a value) ──────────────────
             Hidden without `manage webhook settings`; the server sends no
             payload for it in that case. -->
        <Panel
            v-if="canManageSettings && storage"
            :heading="__('webhook-manager::messages.storage_heading')"
            :subheading="__('webhook-manager::messages.storage_sub')"
        >
            <Card>
                <Field inline :label="__('webhook-manager::messages.storage_active_driver')">
                    <div class="flex items-center gap-2">
                        <Badge
                            :color="storage.driver === 'flat' ? 'green' : 'blue'"
                            :text="storage.driver_label"
                        />
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            {{ storage.source === 'control_panel'
                                ? __('webhook-manager::messages.storage_source_control_panel')
                                : __('webhook-manager::messages.storage_source_config') }}
                        </span>
                    </div>
                </Field>

                <Field v-if="storage.driver === 'flat'" inline
                    :label="__('webhook-manager::messages.storage_flat_path_label')"
                >
                    <Input :model-value="storage.flat_path" read-only class="font-mono text-sm" />
                </Field>

                <Field inline :label="__('webhook-manager::messages.storage_records')">
                    <span class="text-sm text-gray-900 dark:text-gray-100 tabular-nums">{{ storageCountsLine }}</span>
                </Field>

                <Field inline
                    :label="__('webhook-manager::messages.storage_switch_to', { driver: storage.target_label })"
                    :instructions="__('webhook-manager::messages.storage_switch_hint', { driver: storage.target_label })"
                >
                    <Button
                        :text="__('webhook-manager::messages.storage_switch_to', { driver: storage.target_label })"
                        :disabled="switchForm.processing"
                        @click="showSwitch = true"
                    />
                </Field>
            </Card>
        </Panel>

        <!-- ── Raw config panel ─────────────────────────────────────────
             The one answer to "what is this install really running". Secrets
             are masked on the server, not here. -->
        <Panel :heading="__('webhook-manager::messages.cp.settings_raw_heading')">
            <Card>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    {{ __('webhook-manager::messages.cp.settings_raw_sub') }}
                </p>

                <Alert class="mb-4">
                    <template #default>
                        <span>{{ __('webhook-manager::settings.intro', { path: configFilePath }) }}</span>
                        <Button
                            size="sm"
                            variant="default"
                            class="ml-3 shrink-0"
                            :text="copied ? __('webhook-manager::messages.cp.btn_copied') : __('webhook-manager::messages.cp.btn_copy_path')"
                            @click="copyPath"
                        >
                            <template #icon>
                                <!-- `check` is not in the icon set — `checkmark` is. -->
                                <Icon :name="copied ? 'checkmark' : 'clipboard'" />
                            </template>
                        </Button>
                    </template>
                </Alert>

                <CodeEditor
                    :model-value="rawConfig"
                    mode="json"
                    :read-only="true"
                    :line-numbers="true"
                    class="font-mono text-sm"
                />
            </Card>
        </Panel>

        <ConfirmationModal
            v-if="canManageSettings && storage"
            :open="showSwitch"
            :title="__('webhook-manager::messages.storage_switch_to', { driver: storage.target_label })"
            :body-text="__('webhook-manager::messages.storage_switch_hint', { driver: storage.target_label })"
            :button-text="__('webhook-manager::messages.storage_switch_to', { driver: storage.target_label })"
            @confirm="switchStorage"
            @update:open="showSwitch = $event"
        />

    </div>
</template>
