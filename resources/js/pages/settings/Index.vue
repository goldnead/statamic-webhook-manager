<script setup>
/**
 * Webhook Manager settings — a form, not a printout.
 *
 * Every control in the form is generated from `groups`, which the server built
 * from `Support\Settings`: the same definition the validation and the boot-time
 * config override read. That is deliberate. The read-only version this replaced
 * kept its own list of labels, instructions and defaults in JavaScript, so the
 * screen was a second description of `config/webhook-manager.php` that could
 * disagree with it and never say so.
 *
 * Saving is an Inertia visit (`router.patch`), so it brings the progress bar,
 * the flash toast, the unsaved-changes guard and a working back button. The
 * server redirects back and this page is re-rendered from scratch, which is
 * what keeps the screen and the installation from drifting: the values that
 * come back are not always what was typed — a number typed into a text box
 * comes back a number, a list comes back without the blank line the textarea
 * left behind, and a value set back to the shipped default comes back as that
 * default with the stored override deleted — and the diagnostics panel's
 * resolved config tree is rebuilt in the same breath.
 *
 * Two panels are deliberately still read-only. `environment` holds what the
 * deployment owns (env vars, and the inbound prefix that route caching freezes)
 * — shown so it can be checked, not offered so it can be half-changed. Storage
 * is an action rather than a value: switching the driver has to move the stored
 * configuration first, which is what the button below does.
 */
import { ref, computed, watch } from 'vue';
import { Head, router, useForm } from '@statamic/cms/inertia';
import {
    Header,
    Alert,
    Badge,
    Button,
    Icon,
    Field,
    Input,
    Textarea,
    Select,
    Switch,
    Card,
    CodeEditor,
    Panel,
    ConfirmationModal,
} from '@statamic/cms/ui';

const props = defineProps({
    /** Groups + fields, from Support\Settings::groups() */
    groups: { type: Array, required: true },
    /** Current value per dotted config path */
    values: { type: Object, required: true },
    /** PATCH endpoint that writes the form */
    updateUrl: { type: String, required: true },
    /** Deployment-owned settings, shown read-only */
    environment: { type: Array, required: true },
    /** JSON-encoded resolved config tree for the raw-config panel */
    rawConfig: { type: String, required: true },
    /** Absolute path to the config file on disk */
    configFilePath: { type: String, required: true },
    /** Active storage driver + record counts + switch URL */
    storage: { type: Object, required: true },
});

const copied = ref(false);

function copyPath() {
    navigator.clipboard.writeText(props.configFilePath).then(() => {
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    });
}

// ----- the form -------------------------------------------------------

/**
 * A `list` is edited as one textarea of lines, because that is how a set of
 * header or key names is read and pasted. The conversion lives here rather than
 * on the server so the server keeps receiving an array and nothing has to guess
 * at a separator.
 */
function toForm(values) {
    const form = {};

    for (const group of props.groups) {
        for (const field of group.fields) {
            const value = values[field.key];
            form[field.key] = field.type === 'list'
                ? (value ?? []).join('\n')
                : (value ?? (field.type === 'boolean' ? false : ''));
        }
    }

    return form;
}

function fromForm(form) {
    const out = {};

    for (const group of props.groups) {
        for (const field of group.fields) {
            const value = form[field.key];
            out[field.key] = field.type === 'list'
                ? String(value ?? '').split('\n').map((line) => line.trim()).filter(Boolean)
                : value;
        }
    }

    return out;
}

const form = ref(toForm(props.values));
const saved = ref(JSON.stringify(form.value));
const saving = ref(false);
const fieldErrors = ref({});

const dirty = computed(() => JSON.stringify(form.value) !== saved.value);

// An Inertia visit that re-renders this page (a storage switch, a back button)
// hands new props to the same component instance. Without this the form would
// keep showing the values from the visit before it.
watch(() => props.values, (values) => {
    form.value = toForm(values);
    saved.value = JSON.stringify(form.value);
    fieldErrors.value = {};
});

/**
 * Errors that belong to no control on this page. There should be none — every
 * validated key has a field here — but a rule added on the server and not here
 * would otherwise be rejected in silence.
 */
const generalErrors = computed(() => {
    const known = new Set(props.groups.flatMap((g) => g.fields.map((f) => `settings.${f.key}`)));

    return Object.entries(fieldErrors.value)
        .filter(([key]) => !known.has(key))
        .map(([, messages]) => (Array.isArray(messages) ? messages[0] : messages));
});

function errorFor(field) {
    const messages = fieldErrors.value[`settings.${field.key}`];

    return Array.isArray(messages) ? messages[0] : messages;
}

/**
 * Save through the Inertia router, not through axios.
 *
 * An axios call here was a page mutation dressed up as an API call: no
 * progress bar, no flash toast, no unsaved-changes guard, and — the part that
 * actually misled people — no refresh of anything but the form. `rawConfig`,
 * the resolved config tree in the diagnostics panel below, went on printing
 * the state from before the save. `router.patch` visits, the server redirects
 * back, and every prop on the page is rebuilt; the watcher on `values` then
 * reloads the form from the answer, so the screen still shows what the
 * installation holds rather than what was typed.
 */
function save() {
    if (saving.value) return;

    saving.value = true;
    fieldErrors.value = {};

    router.patch(props.updateUrl, { settings: fromForm(form.value) }, {
        preserveScroll: true,
        onSuccess: () => { fieldErrors.value = {}; },
        onError: (errors) => {
            fieldErrors.value = errors ?? {};
            // A rejection with no error bag (500, a dropped connection) still
            // has to say something; an empty banner is how a failed save looks
            // exactly like a successful one.
            if (!Object.keys(fieldErrors.value).length) {
                fieldErrors.value = { 'settings.__failed': [__('webhook-manager::settings.save_failed')] };
            }
        },
        onFinish: () => { saving.value = false; },
    });
}

// ----- storage driver switch -----------------------------------------

const showSwitch = ref(false);

// Target driver to switch to (the other store). Uses form.submit('post', url)
// — the proven submission helper in this Inertia build (the dynamic-verb
// form.post()/router.post() helpers misbehave here, same as the edit forms).
const switchForm = useForm({ driver: props.storage.target });

const storageCountsLine = computed(() => {
    const c = props.storage.counts || {};
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

// The driver switch is the one thing on this page that is not the settings
// form. It can be refused too, and its rejection has no field to appear in.
const switchErrors = computed(() => switchForm.errors ?? {});
</script>

<template>
    <Head :title="[__('webhook-manager::messages.cp.page_settings'), __('webhook-manager::messages.cp.app_name')]" />

    <div class="max-w-page mx-auto">

        <!-- ── Page header ──────────────────────────────────────────── -->
        <Header :title="__('webhook-manager::settings.title')" icon="sliders-horizontal">
            <Button
                variant="primary"
                :text="saving ? __('webhook-manager::settings.saving') : __('webhook-manager::settings.save')"
                :disabled="saving || !dirty"
                data-settings-save
                @click="save"
            />
        </Header>

        <Alert v-if="generalErrors.length" variant="error" class="mb-6" data-settings-form-errors>
            <ul class="list-disc list-inside space-y-0.5">
                <li v-for="(message, i) in generalErrors" :key="i">{{ message }}</li>
            </ul>
        </Alert>

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

        <!-- ── Where the untouched values come from ───────────────────
             No `variant`: `info` is not one of Alert's four
             (default/warning/error/success). This describes how the screen
             works — what an untouched value follows, what a changed one
             stores. It is on screen on every visit, and a banner that is
             permanently styled as a warning stops being read as one. -->
        <Alert class="mb-6">
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
                        <!-- `check` is not in the icon set — `checkmark` is.
                             Icon renders nothing for an unknown name, so the
                             button lost its icon at the one moment it was
                             meant to confirm something. -->
                        <Icon :name="copied ? 'checkmark' : 'clipboard'" />
                    </template>
                </Button>
            </template>
        </Alert>

        <div class="space-y-6">

            <!-- ── The form ─────────────────────────────────────────── -->
            <Panel
                v-for="group in groups"
                :key="group.title"
                :heading="group.title"
            >
                <Card>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">{{ group.description }}</p>

                    <!-- The marker sits on a wrapper, not on `Field`: Field
                         renders its own root and does not pass stray attributes
                         through, so the hook a test reaches for would not exist
                         in the DOM. -->
                    <div
                        v-for="field in group.fields"
                        :key="field.key"
                        class="mb-5 last:mb-0"
                        :data-settings-field="field.key"
                    >
                        <Field
                            :label="field.label"
                            :instructions="field.description"
                        >
                            <Switch
                                v-if="field.type === 'boolean'"
                                :model-value="form[field.key]"
                                @update:model-value="form[field.key] = $event"
                            />
                            <Select
                                v-else-if="field.type === 'select'"
                                :model-value="form[field.key]"
                                :options="field.options"
                                @update:model-value="form[field.key] = $event"
                            />
                            <Textarea
                                v-else-if="field.type === 'list'"
                                :model-value="form[field.key]"
                                :rows="6"
                                class="font-mono text-sm"
                                @update:model-value="form[field.key] = $event"
                            />
                            <Input
                                v-else
                                :model-value="form[field.key]"
                                :type="field.type === 'integer' ? 'number' : 'text'"
                                :input-attrs="field.min !== undefined ? { min: field.min } : {}"
                                :placeholder="field.nullable ? __('webhook-manager::settings.default_placeholder') : ''"
                                @update:model-value="form[field.key] = $event"
                            />

                            <p
                                v-if="errorFor(field)"
                                class="mt-1 text-sm text-red-600 dark:text-red-400"
                                :data-settings-field-error="field.key"
                            >{{ errorFor(field) }}</p>
                        </Field>
                    </div>
                </Card>
            </Panel>

            <!-- ── Deployment-owned, shown but not editable ─────────── -->
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

            <!-- ── Storage driver (an action, not a value) ──────────── -->
            <Panel :heading="__('webhook-manager::messages.storage_heading')" :subheading="__('webhook-manager::messages.storage_sub')">
                <Card>
                    <Field inline
                        :label="__('webhook-manager::messages.storage_active_driver')"
                    >
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

                    <Field inline
                        :label="__('webhook-manager::messages.storage_records')"
                    >
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
        </div>

        <!-- ── Raw config panel ─────────────────────────────────────── -->
        <Panel :heading="__('webhook-manager::messages.cp.settings_raw_heading')" class="mt-6">
            <Card>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    {{ __('webhook-manager::messages.cp.settings_raw_sub') }}
                </p>
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
            :open="showSwitch"
            :title="__('webhook-manager::messages.storage_switch_to', { driver: storage.target_label })"
            :body-text="__('webhook-manager::messages.storage_switch_hint', { driver: storage.target_label })"
            :button-text="__('webhook-manager::messages.storage_switch_to', { driver: storage.target_label })"
            @confirm="switchStorage"
            @update:open="showSwitch = $event"
        />

    </div>
</template>
