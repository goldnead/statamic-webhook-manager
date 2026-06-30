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
    CheckboxGroup,
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

/**
 * Inbound endpoint edit/create page.
 *
 * Layout follows Statamic's PublishForm/Tabs convention: configuration
 * is split into 7 tabs so users navigate rather than scroll a long page.
 *
 * Auth config is write-only on the wire: PHP exposes only an
 * `auth_configured` flag. Submitting an empty `auth_config_json` keeps
 * the stored secret untouched (see InboundController::normalizeAuthConfig).
 *
 * tabsWithErrors auto-switches to the first tab that has a validation
 * error so users don't miss failures on inactive tabs.
 */
const props = defineProps({
    endpoint: { type: Object, required: true },
    authOptions: { type: Object, required: true },
    actionOptions: { type: Object, required: true },
    isNew: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
    saveUrl: { type: String, required: true },
    deleteUrl: { type: String, default: null },
    toggleUrl: { type: String, default: null },
    testUrl: { type: String, default: null },
    indexUrl: { type: String, required: true },
    routePrefix: { type: String, default: '' },
});

const form = useForm({
    name: props.endpoint.name ?? '',
    handle: props.endpoint.handle ?? '',
    description: props.endpoint.description ?? '',
    enabled: props.endpoint.enabled ?? true,
    path: props.endpoint.path ?? props.endpoint.handle ?? '',
    allowed_methods: props.endpoint.allowed_methods ?? ['POST'],
    auth_type: props.endpoint.auth_type ?? 'static_header',
    auth_config_json: '',
    expected_content_type: props.endpoint.expected_content_type ?? 'application/json',
    max_payload_kb: props.endpoint.max_payload_kb ?? 512,
    replay_protection_enabled: props.endpoint.replay_protection_enabled ?? false,
    logging_mode: props.endpoint.logging_mode ?? 'partial',
    mapping_config_json: jsonOrEmpty(props.endpoint.mapping_config),
    action_type: props.endpoint.action_type ?? 'noop',
    action_config_json: jsonOrEmpty(props.endpoint.action_config),
    response_config_json: jsonOrEmpty(props.endpoint.response_config),
});

const activeTab = ref('general');
const showDelete = ref(false);
const testing = ref(false);
const testResult = ref(null);
const samplePayload = ref('{\n  "id": 1,\n  "name": "Test"\n}');

const pageTitle = computed(() =>
    props.isNew ? __('Create inbound endpoint') : (props.endpoint.name || __('Inbound endpoint'))
);
const saveLabel = computed(() => props.isNew ? __('Create') : __('Save'));

const fullUrl = computed(() => {
    const base = props.routePrefix ? `/${props.routePrefix}` : '';
    return `${base}/${form.path || form.handle || ':handle'}`;
});

// Surface server-side validation errors on the right tab.
// Without this, a user on the General tab misses an error on Auth.
const tabsWithErrors = computed(() => {
    const map = {
        general:  ['name', 'handle', 'description', 'enabled', 'path'],
        auth:     ['auth_type', 'auth_config_json', 'auth_config'],
        methods:  ['allowed_methods', 'allowed_methods.*'],
        mapping:  ['mapping_config', 'mapping_config_json'],
        action:   ['action_type', 'action_config', 'action_config_json'],
        response: ['response_config', 'response_config_json'],
        test:     [],
    };
    const tabs = new Set();
    for (const [tab, keys] of Object.entries(map)) {
        if (keys.some(k => form.errors[k])) tabs.add(tab);
    }
    return tabs;
});

watch(() => form.hasErrors, hasErrors => {
    if (!hasErrors) return;
    const order = ['general', 'auth', 'methods', 'mapping', 'action', 'response'];
    const first = order.find(t => tabsWithErrors.value.has(t));
    if (first) activeTab.value = first;
});

// Dynamic placeholder for the auth_config JSON editor
const authPlaceholder = computed(() => {
    switch (form.auth_type) {
        case 'static_header': return '{ "header": "X-Webhook-Secret", "value": "your-secret" }';
        case 'bearer':        return '{ "token": "your-bearer-token" }';
        case 'basic':         return '{ "username": "user", "password": "pass" }';
        case 'hmac':          return '{ "secret": "your-shared-secret", "algorithm": "sha256" }';
        case 'ip_allowlist':  return '{ "ips": ["1.2.3.4", "5.6.7.8"] }';
        default:              return '{}';
    }
});

const authInstructions = computed(() => {
    if (form.auth_type === 'none') {
        return __('No authentication. Anyone can post to this endpoint.');
    }
    if (props.endpoint.auth_configured) {
        return __('A secret is already configured. Leave blank to keep it. Paste new JSON to replace — the value is encrypted at rest.');
    }
    return __('Stored encrypted. Format depends on the auth type — see the placeholder for an example.');
});

const allMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

const allowedMethodOptions = allMethods.map(m => ({ value: m, label: m }));

/**
 * JSON placeholders for the advanced config CodeEditors.
 *
 * Defined as JS template literals (not inline in the template) because
 * a v-bind expression wrapped in double-quoted attribute can't include
 * escaped double quotes — the SFC parser bails out with "Unterminated
 * string constant".
 */
const mappingPlaceholder = `{
  "output_field": "$.input_field"
}`;

const actionPlaceholder = `{
  "collection": "blog",
  "title": "$.title"
}`;

const responsePlaceholder = `{
  "ok": true
}`;

function jsonOrEmpty(value) {
    if (!value) return '';
    try { return JSON.stringify(value, null, 2); }
    catch (e) { return ''; }
}

/**
 * Statamic's <Select> wraps <Combobox>, which expects `:options` as an
 * Array of `{ value, label }` objects. PHP-side Registry::options()
 * returns `{ key: label }` Object — we normalise to Array here.
 */
function objectToOptions(obj) {
    if (!obj || typeof obj !== 'object') return [];
    return Object.entries(obj).map(([value, label]) => ({ value, label }));
}

const authOptionsArray = computed(() => objectToOptions(props.authOptions));
const actionOptionsArray = computed(() => objectToOptions(props.actionOptions));

function save() {
    if (!props.saveUrl) {
        console.error(
            '[webhook-manager] Inbound/Edit: saveUrl prop is missing — cannot submit.',
            'Inertia props received:', { isNew: props.isNew, saveUrl: props.saveUrl, indexUrl: props.indexUrl }
        );
        return;
    }
    const verb = props.isNew ? 'post' : 'patch';
    form.submit(verb, props.saveUrl, { preserveScroll: true });
}

function destroy() {
    if (!props.deleteUrl) {
        console.error('[webhook-manager] Inbound/Edit: deleteUrl prop is missing — cannot delete.');
        return;
    }
    router.delete(props.deleteUrl, {
        preserveScroll: true,
        onSuccess: () => { showDelete.value = false; },
    });
}

async function runTest() {
    if (!props.testUrl) return;
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
                ?? __('Test request failed.'),
            mapped: {},
            data: {},
        };
    } finally {
        testing.value = false;
    }
}
</script>

<template>
    <Head :title="[pageTitle, __('Inbound'), __('Webhook Manager')]" />

    <div class="max-w-5xl 3xl:max-w-6xl mx-auto" data-max-width-wrapper>

        <Header :title="pageTitle" icon="download">
            <template #actions>
                <StatusIndicator
                    v-if="!isNew"
                    :active="endpoint.enabled"
                    :active-label="__('Active')"
                    :inactive-label="__('Disabled')"
                    class="mr-2"
                />
                <Button
                    v-if="!isNew && deleteUrl"
                    variant="danger"
                    :text="__('Delete')"
                    @click="showDelete = true"
                />
                <Button
                    variant="primary"
                    :text="saveLabel"
                    :loading="form.processing"
                    @click="save"
                />
                <CommandPaletteItem category="Actions" :text="saveLabel" @click="save" />
            </template>
        </Header>

        <!-- Global validation error banner -->
        <Alert v-if="form.hasErrors" variant="danger" class="mb-4">
            <ul class="list-disc list-inside space-y-0.5">
                <li v-for="(err, key) in form.errors" :key="key">{{ err }}</li>
            </ul>
        </Alert>

        <Tabs v-model="activeTab">
            <TabList>
                <TabTrigger value="general" :class="{ 'text-red-500': tabsWithErrors.has('general') }">
                    {{ __('General') }}
                </TabTrigger>
                <TabTrigger value="auth" :class="{ 'text-red-500': tabsWithErrors.has('auth') }">
                    {{ __('Authentication') }}
                </TabTrigger>
                <TabTrigger value="methods" :class="{ 'text-red-500': tabsWithErrors.has('methods') }">
                    {{ __('Allowed Methods') }}
                </TabTrigger>
                <TabTrigger value="mapping" :class="{ 'text-red-500': tabsWithErrors.has('mapping') }">
                    {{ __('Mapping') }}
                </TabTrigger>
                <TabTrigger value="action" :class="{ 'text-red-500': tabsWithErrors.has('action') }">
                    {{ __('Action') }}
                </TabTrigger>
                <TabTrigger value="response" :class="{ 'text-red-500': tabsWithErrors.has('response') }">
                    {{ __('Response') }}
                </TabTrigger>
                <TabTrigger value="test">
                    {{ __('Test') }}
                </TabTrigger>
            </TabList>

            <!-- ── GENERAL ── -->
            <TabContent value="general">
                <Panel>
                    <div class="bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-gray-700/80 shadow-sm p-6 space-y-6">
                    <Field inline :label="__('Name')" :error="form.errors.name" required>
                        <Input v-model="form.name" :placeholder="__('My Inbound Endpoint')" />
                    </Field>

                    <Field inline :label="__('Handle')" :error="form.errors.handle" required>
                        <Input v-model="form.handle" :placeholder="__('my-inbound-endpoint')" class="font-mono" />
                    </Field>

                    <Field inline :label="__('Path')" :error="form.errors.path" :instructions="__('The URL path segment for this endpoint.')">
                        <Input v-model="form.path" :placeholder="__('my-endpoint')" class="font-mono" />
                        <div class="mt-1 text-xs text-gray-500 font-mono">
                            {{ __('Full URL:') }} <span class="text-blue-600 dark:text-blue-400">{{ fullUrl }}</span>
                        </div>
                    </Field>

                    <Field inline :label="__('Enabled')" :error="form.errors.enabled">
                        <Switch v-model="form.enabled">{{ __('Enabled') }}</Switch>
                    </Field>

                    <Field inline :label="__('Description')" :error="form.errors.description">
                        <Textarea v-model="form.description" :rows="3" />
                    </Field>
                    </div>
                </Panel>
            </TabContent>

            <!-- ── AUTHENTICATION ── -->
            <TabContent value="auth">
                <Panel>
                    <div class="bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-gray-700/80 shadow-sm p-6 space-y-6">
                    <Field inline :label="__('Auth Type')" :error="form.errors.auth_type" required>
                        <Select v-model="form.auth_type" :options="authOptionsArray" />
                    </Field>

                    <Field inline
                        v-if="form.auth_type !== 'none'"
                        :label="__('Auth Config (JSON)')"
                        :error="form.errors.auth_config_json || form.errors.auth_config"
                        :instructions="authInstructions"
                    >
                        <CodeEditor
                            v-model="form.auth_config_json"
                            mode="json"
                            :placeholder="authPlaceholder"
                            :min-lines="4"
                        />
                    </Field>
                    </div>
                </Panel>
            </TabContent>

            <!-- ── ALLOWED METHODS ── -->
            <TabContent value="methods">
                <Panel>
                    <div class="bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-gray-700/80 shadow-sm p-6 space-y-6">
                    <Field inline
                        :label="__('Allowed HTTP Methods')"
                        :error="form.errors.allowed_methods"
                        :instructions="__('Select which HTTP methods this endpoint will accept.')"
                    >
                        <CheckboxGroup
                            v-model="form.allowed_methods"
                            :options="allowedMethodOptions"
                        />
                    </Field>
                    </div>
                </Panel>
            </TabContent>

            <!-- ── MAPPING ── -->
            <TabContent value="mapping">
                <Panel>
                    <div class="bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-gray-700/80 shadow-sm p-6 space-y-6">
                    <Alert variant="info" class="mb-4">
                        {{ __('Define a JSON mapping to transform the incoming payload before it is passed to the action.') }}
                        <a
                            href="https://github.com/goldnead/statamic-webhook-manager"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="underline ml-1"
                        >{{ __('View mapping documentation') }}</a>
                    </Alert>

                    <Field inline
                        :label="__('Mapping Config (JSON)')"
                        :error="form.errors.mapping_config_json || form.errors.mapping_config"
                        :instructions="__('Map incoming fields to output fields. Leave empty to pass the payload through unchanged.')"
                    >
                        <CodeEditor
                            v-model="form.mapping_config_json"
                            mode="json"
                            :placeholder="mappingPlaceholder"
                            :min-lines="8"
                        />
                    </Field>
                    </div>
                </Panel>
            </TabContent>

            <!-- ── ACTION ── -->
            <TabContent value="action">
                <Panel>
                    <div class="bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-gray-700/80 shadow-sm p-6 space-y-6">
                    <Field inline :label="__('Action Type')" :error="form.errors.action_type" required>
                        <Select v-model="form.action_type" :options="actionOptionsArray" />
                    </Field>

                    <Field inline
                        v-if="form.action_type && form.action_type !== 'noop'"
                        :label="__('Action Config (JSON)')"
                        :error="form.errors.action_config_json || form.errors.action_config"
                        :instructions="__('Configuration for the selected action. Format depends on the action type.')"
                    >
                        <CodeEditor
                            v-model="form.action_config_json"
                            mode="json"
                            :placeholder="actionPlaceholder"
                            :min-lines="6"
                        />
                    </Field>
                    </div>
                </Panel>
            </TabContent>

            <!-- ── RESPONSE ── -->
            <TabContent value="response">
                <Panel>
                    <div class="bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-gray-700/80 shadow-sm p-6 space-y-6">
                    <Field inline
                        :label="__('Response Config (JSON)')"
                        :error="form.errors.response_config_json || form.errors.response_config"
                        :instructions="__('Customise the HTTP response returned to the caller. Leave empty for the default 200 OK.')"
                    >
                        <CodeEditor
                            v-model="form.response_config_json"
                            mode="json"
                            :placeholder="responsePlaceholder"
                            :min-lines="6"
                        />
                    </Field>
                    </div>
                </Panel>
            </TabContent>

            <!-- ── TEST ── -->
            <TabContent value="test">
                <Panel :heading="__('Send a test payload')">
                    <Field inline
                        :label="__('Sample Payload (JSON)')"
                        :instructions="__('This payload will be processed through the mapping and action pipeline.')"
                    >
                        <CodeEditor
                            v-model="samplePayload"
                            mode="json"
                            :min-lines="6"
                        />
                    </Field>

                    <div class="mt-4">
                        <Button
                            variant="default"
                            :text="__('Run test')"
                            :loading="testing"
                            :disabled="!testUrl || testing"
                            @click="runTest"
                        />
                        <span v-if="!testUrl" class="ml-3 text-sm text-gray-400">
                            {{ __('Save the endpoint first to enable testing.') }}
                        </span>
                    </div>

                    <!-- Test result panels -->
                    <template v-if="testResult">
                        <Alert
                            :variant="testResult.ok ? 'success' : 'danger'"
                            class="mt-4"
                        >
                            {{ testResult.message || (testResult.ok ? __('Test successful.') : __('Test failed.')) }}
                        </Alert>

                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <Panel :heading="__('Mapped Payload')">
                                <CodeEditor
                                    :model-value="JSON.stringify(testResult.mapped ?? {}, null, 2)"
                                    mode="json"
                                    read-only
                                    :min-lines="6"
                                />
                            </Panel>

                            <Panel :heading="__('Action Result')">
                                <CodeEditor
                                    :model-value="JSON.stringify(testResult.data ?? {}, null, 2)"
                                    mode="json"
                                    read-only
                                    :min-lines="6"
                                />
                            </Panel>
                        </div>

                        <Panel v-if="testResult.errors?.length" :heading="__('Errors')" class="mt-4">
                            <ul class="list-disc list-inside text-sm text-red-600 space-y-1">
                                <li v-for="(err, i) in testResult.errors" :key="i">{{ err }}</li>
                            </ul>
                        </Panel>
                    </template>
                </Panel>
            </TabContent>
        </Tabs>

        <!-- Delete confirmation -->
        <ConfirmationModal
            v-if="showDelete"
            :title="__('Delete endpoint?')"
            :body-text="__('This action cannot be undone.')"
            :confirm-text="__('Delete')"
            confirm-variant="danger"
            @confirm="destroy"
            @cancel="showDelete = false"
        />
    </div>
</template>
