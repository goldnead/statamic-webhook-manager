<script setup>
import { ref, computed } from 'vue';
import { Head } from '@statamic/cms/inertia';
import { useForm, router } from '@inertiajs/vue3';
import { Header, Button, Panel, Field, Input, Badge, Alert, ConfirmationModal } from '@statamic/cms/ui';

const props = defineProps({
    endpoint: { type: Object, required: true },
    authOptions: { type: Object, required: true },
    actionOptions: { type: Object, required: true },
    isNew: { type: Boolean, default: false },
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

const showDelete = ref(false);
const testing = ref(false);
const testResult = ref(null);
const samplePayload = ref('{\n  "id": 1,\n  "name": "Test"\n}');

const pageTitle = computed(() => props.isNew
    ? __('Create inbound endpoint')
    : props.endpoint.name);

const fullUrl = computed(() => {
    const base = props.routePrefix ? `/${props.routePrefix}` : '';
    return `${base}/${form.handle || ':handle'}`;
});

const allMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

function jsonOrEmpty(value) {
    if (!value) return '';
    try { return JSON.stringify(value, null, 2); }
    catch (e) { return ''; }
}

function toggleMethod(method) {
    const idx = form.allowed_methods.indexOf(method);
    if (idx === -1) form.allowed_methods.push(method);
    else form.allowed_methods.splice(idx, 1);
}

function save() {
    const verb = props.isNew ? 'post' : 'patch';
    form[verb](props.saveUrl, { preserveScroll: true });
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

function destroy() {
    router.delete(props.deleteUrl, {
        preserveScroll: true,
        onSuccess: () => { showDelete.value = false; },
    });
}
</script>

<template>
    <Head :title="pageTitle" />

    <Header :title="pageTitle" icon="incoming">
        <Badge v-if="!isNew" :color="endpoint.enabled ? 'green' : 'gray'">
            {{ endpoint.enabled ? __('Enabled') : __('Disabled') }}
        </Badge>
        <div class="flex gap-2">
            <Button variant="primary" :loading="form.processing" @click="save">
                {{ __('Save') }}
            </Button>
        </div>
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
            <Field :label="__('Handle')" id="handle" :required="true" :error="form.errors.handle"
                   :instructions="__('Lowercase, used in the inbound URL.')">
                <Input id="handle" v-model="form.handle" pattern="[a-z0-9_-]+" />
            </Field>
            <Field :label="__('Description')" id="description" class="md:col-span-2">
                <textarea id="description" v-model="form.description" rows="2" class="input-text w-full"></textarea>
            </Field>
            <Field :label="__('Status')" id="enabled">
                <label class="flex items-center gap-2">
                    <input type="checkbox" v-model="form.enabled" />
                    <span>{{ __('Enabled') }}</span>
                </label>
            </Field>
        </div>
    </Panel>

    <Panel :heading="__('Endpoint')" class="mb-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4">
            <Field :label="__('Public URL')" id="path" class="md:col-span-2"
                   :instructions="__('Senders POST to this URL. The path mirrors the handle by default.')">
                <code class="block text-sm bg-gray-100 dark:bg-gray-800 p-2 rounded">{{ fullUrl }}</code>
            </Field>
            <Field :label="__('Allowed methods')" id="allowed_methods" class="md:col-span-2"
                   :error="form.errors.allowed_methods">
                <div class="flex gap-3 flex-wrap">
                    <label v-for="m in allMethods" :key="m" class="flex items-center gap-2">
                        <input type="checkbox"
                               :checked="form.allowed_methods.includes(m)"
                               @change="toggleMethod(m)" />
                        <span class="text-sm">{{ m }}</span>
                    </label>
                </div>
            </Field>
            <Field :label="__('Expected content type')" id="expected_content_type"
                   :error="form.errors.expected_content_type">
                <select id="expected_content_type" v-model="form.expected_content_type" class="input-text">
                    <option value="application/json">application/json</option>
                    <option value="application/x-www-form-urlencoded">application/x-www-form-urlencoded</option>
                </select>
            </Field>
            <Field :label="__('Max payload (KB)')" id="max_payload_kb" :error="form.errors.max_payload_kb">
                <Input id="max_payload_kb" v-model.number="form.max_payload_kb" type="number" min="1" max="65536" />
            </Field>
            <Field :label="__('Replay protection')" id="replay_protection_enabled" class="md:col-span-2"
                   :instructions="__('Reject duplicate requests detected via Idempotency-Key, signature header, or body hash.')">
                <label class="flex items-center gap-2">
                    <input type="checkbox" v-model="form.replay_protection_enabled" />
                    <span>{{ __('Enabled') }}</span>
                </label>
            </Field>
            <Field :label="__('Logging mode')" id="logging_mode">
                <select id="logging_mode" v-model="form.logging_mode" class="input-text">
                    <option value="full">{{ __('Full') }}</option>
                    <option value="partial">{{ __('Partial') }}</option>
                    <option value="none">{{ __('None') }}</option>
                </select>
            </Field>
        </div>
    </Panel>

    <Panel :heading="__('Authentication')" class="mb-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4">
            <Field :label="__('Type')" id="auth_type" :error="form.errors.auth_type">
                <select id="auth_type" v-model="form.auth_type" class="input-text">
                    <option v-for="(label, value) in authOptions" :key="value" :value="value">{{ label }}</option>
                </select>
            </Field>
            <Field :label="__('Auth config (JSON)')" id="auth_config_json" class="md:col-span-2"
                   :instructions="endpoint.auth_configured
                       ? __('A secret is already configured. Leave blank to keep it. To replace, paste new JSON below — it will be encrypted at rest.')
                       : __('Paste JSON like { \&quot;header\&quot;: \&quot;X-API-Key\&quot;, \&quot;value\&quot;: \&quot;your-secret\&quot; } or { \&quot;secret\&quot;: \&quot;...\&quot; }. Stored encrypted.')">
                <textarea id="auth_config_json" v-model="form.auth_config_json" rows="3"
                          class="input-text w-full font-mono text-sm"
                          placeholder='{ "header": "X-API-Key", "value": "your-secret" }'></textarea>
            </Field>
        </div>
    </Panel>

    <Panel :heading="__('Mapping')" class="mb-4">
        <Field :label="__('Mapping config (JSON)')" id="mapping_config_json" class="p-4"
               :error="form.errors.mapping_config_json"
               :instructions="__('Map inbound payload paths to internal keys. Each value is { path, default?, transform?, type?, required? }. Leave empty to pass the payload through unchanged.')">
            <textarea id="mapping_config_json" v-model="form.mapping_config_json" rows="8"
                      class="input-text w-full font-mono text-sm"
                      placeholder='{ "email": { "path": "contact.email", "required": true }, "title": { "path": "contact.name" } }'></textarea>
        </Field>
    </Panel>

    <Panel :heading="__('Action')" class="mb-4">
        <div class="grid grid-cols-1 gap-4 p-4">
            <Field :label="__('Type')" id="action_type" :error="form.errors.action_type">
                <select id="action_type" v-model="form.action_type" class="input-text">
                    <option v-for="(label, value) in actionOptions" :key="value" :value="value">{{ label }}</option>
                </select>
            </Field>
            <Field :label="__('Action config (JSON)')" id="action_config_json"
                   :instructions="__('Configuration for the selected handler. For example, { collection: posts, slug_field: slug } for create_entry.')">
                <textarea id="action_config_json" v-model="form.action_config_json" rows="6"
                          class="input-text w-full font-mono text-sm"
                          placeholder='{ "collection": "posts" }'></textarea>
            </Field>
        </div>
    </Panel>

    <Panel :heading="__('Response')" class="mb-4">
        <Field :label="__('Response config (JSON)')" id="response_config_json" class="p-4"
               :instructions="__('Override response status codes. Defaults to 200 / 422 if empty.')">
            <textarea id="response_config_json" v-model="form.response_config_json" rows="3"
                      class="input-text w-full font-mono text-sm"
                      placeholder='{ "success_status": 200, "failure_status": 422 }'></textarea>
        </Field>
    </Panel>

    <Panel v-if="!isNew && testUrl" :heading="__('Test')" class="mb-4">
        <div class="p-4">
            <Field :label="__('Sample payload (JSON)')" id="sample_payload"
                   :instructions="__('Run mapping and action against this payload. Auth is bypassed — you are already authorised in the CP.')">
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

            <div v-if="testResult && (testResult.mapped || testResult.data)" class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <h4 class="text-xs uppercase font-semibold text-gray-600 mb-1">{{ __('Mapped payload') }}</h4>
                    <pre class="text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-auto">{{ JSON.stringify(testResult.mapped, null, 2) }}</pre>
                </div>
                <div>
                    <h4 class="text-xs uppercase font-semibold text-gray-600 mb-1">{{ __('Action result') }}</h4>
                    <pre class="text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-auto">{{ JSON.stringify(testResult.data, null, 2) }}</pre>
                </div>
            </div>
            <div v-if="testResult && testResult.errors && testResult.errors.length" class="mt-3">
                <h4 class="text-xs uppercase font-semibold text-red-600 mb-1">{{ __('Errors') }}</h4>
                <ul class="list-disc list-inside text-sm text-red-600">
                    <li v-for="(err, idx) in testResult.errors" :key="idx">{{ err }}</li>
                </ul>
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
        :title="__('Delete endpoint')"
        :body-text="__('This permanently removes the endpoint configuration. Past inbound logs are kept.')"
        :button-text="__('Delete')"
        :danger="true"
        @confirm="destroy"
        @update:open="showDelete = $event"
    />
</template>
