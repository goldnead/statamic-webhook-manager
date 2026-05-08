<script setup>
import { ref, computed } from 'vue';
import { Head } from '@statamic/cms/inertia';
import { useForm, router } from '@inertiajs/vue3';
import { Header, Button, Panel, Field, Input, Badge, Alert, ConfirmationModal } from '@statamic/cms/ui';

const props = defineProps({
    webhook: { type: Object, required: true },
    triggerOptions: { type: Object, required: true },
    authOptions: { type: Object, required: true },
    availableTemplates: { type: Object, default: () => ({}) },
    isNew: { type: Boolean, default: false },
    saveUrl: { type: String, required: true },
    deleteUrl: { type: String, default: null },
    toggleUrl: { type: String, default: null },
    testUrl: { type: String, default: null },
    indexUrl: { type: String, required: true },
});

const form = useForm({
    name: props.webhook.name ?? '',
    handle: props.webhook.handle ?? '',
    description: props.webhook.description ?? '',
    enabled: props.webhook.enabled ?? true,
    trigger_type: props.webhook.trigger_type ?? Object.keys(props.triggerOptions)[0],
    url: props.webhook.url ?? '',
    method: props.webhook.method ?? 'POST',
    timeout_seconds: props.webhook.timeout_seconds ?? 15,
    follow_redirects: props.webhook.follow_redirects ?? true,
    auth_type: props.webhook.auth_type ?? 'none',
    auth_config_json: '',
    payload_type: props.webhook.payload_type ?? 'raw_json',
    payload_template: props.webhook.payload_template ?? '',
    payload_template_handle: props.webhook.payload_template_handle ?? '',
    queue_enabled: props.webhook.queue_enabled ?? true,
    log_body_mode: props.webhook.log_body_mode ?? 'partial',
});

// Body source: 'inline' uses the textarea below, 'library' picks an
// existing webhook_templates entry. Persisting both is allowed; the
// renderer prefers the library entry (see HttpRequestFactory::buildBody).
const bodySource = ref(form.payload_template_handle ? 'library' : 'inline');

function onBodySourceChange(value) {
    bodySource.value = value;
    if (value === 'inline') {
        form.payload_template_handle = '';
    }
}

const showDelete = ref(false);
const testing = ref(false);
const testResult = ref(null);

const pageTitle = computed(() => props.isNew
    ? __('Create outbound webhook')
    : props.webhook.name);

function save() {
    const verb = props.isNew ? 'post' : 'patch';
    form[verb](props.saveUrl, {
        preserveScroll: true,
        onSuccess: (page) => {
            // Inertia auto-follows redirect responses; useForm clears errors.
            // Nothing else to do here.
        },
    });
}

async function runTest() {
    if (!props.testUrl) return;
    testing.value = true;
    testResult.value = null;
    try {
        const res = await window.axios.post(props.testUrl, { sample_payload: {} });
        testResult.value = res.data;
    } catch (e) {
        testResult.value = { ok: false, error_message: e?.response?.data?.message ?? e.message };
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

    <Header :title="pageTitle" icon="outgoing">
        <Badge v-if="!isNew" :color="webhook.enabled ? 'green' : 'gray'">
            {{ webhook.enabled ? __('Enabled') : __('Disabled') }}
        </Badge>
        <div class="flex gap-2">
            <Button v-if="!isNew && testUrl" variant="default" :loading="testing" @click="runTest">
                {{ __('Test') }}
            </Button>
            <Button variant="primary" :loading="form.processing" @click="save">
                {{ __('Save') }}
            </Button>
        </div>
    </Header>

    <Alert v-if="testResult" :variant="testResult.ok ? 'success' : 'error'" class="mb-4"
           :heading="testResult.ok ? __('Test request succeeded') : __('Test request failed')"
           :text="`HTTP ${testResult.response_status ?? '—'} — ${testResult.duration_ms ?? '?'}ms${testResult.error_message ? ' — ' + testResult.error_message : ''}`" />

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

    <Panel :heading="__('Trigger')" class="mb-4">
        <Field :label="__('Trigger type')" id="trigger_type" :required="true" :inline="true"
               :error="form.errors.trigger_type"
               :instructions="__('Internal event that fires this webhook.')">
            <select id="trigger_type" v-model="form.trigger_type" class="input-text">
                <option v-for="(label, value) in triggerOptions" :key="value" :value="value">
                    {{ label }}
                </option>
            </select>
        </Field>
    </Panel>

    <Panel :heading="__('Destination')" class="mb-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4">
            <Field :label="__('URL')" id="url" :required="true" class="md:col-span-2"
                   :error="form.errors.url"
                   :instructions="__('The destination URL the webhook will POST to.')">
                <Input id="url" v-model="form.url" type="url" />
            </Field>
            <Field :label="__('Method')" id="method" :error="form.errors.method">
                <select id="method" v-model="form.method" class="input-text">
                    <option v-for="m in ['POST','GET','PUT','PATCH','DELETE']" :key="m" :value="m">{{ m }}</option>
                </select>
            </Field>
            <Field :label="__('Timeout (seconds)')" id="timeout" :error="form.errors.timeout_seconds">
                <Input id="timeout" v-model.number="form.timeout_seconds" type="number" min="1" max="120" />
            </Field>
            <Field :label="__('Follow redirects')" id="follow_redirects" class="md:col-span-2">
                <label class="flex items-center gap-2">
                    <input type="checkbox" v-model="form.follow_redirects" />
                    <span>{{ __('Follow HTTP redirects') }}</span>
                </label>
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
                   :instructions="webhook.auth_configured
                       ? __('A secret is already configured. Leave blank to keep it. To replace, paste new JSON below — the value will be encrypted at rest.')
                       : __('Paste JSON like { \&quot;secret\&quot;: \&quot;your-secret\&quot; } or { \&quot;token\&quot;: \&quot;...\&quot; }. Stored encrypted.')">
                <textarea id="auth_config_json" v-model="form.auth_config_json" rows="3"
                          class="input-text w-full font-mono text-sm"
                          placeholder='{ "secret": "your-secret" }'></textarea>
            </Field>
        </div>
    </Panel>

    <Panel :heading="__('Payload')" class="mb-4">
        <div class="grid grid-cols-1 gap-4 p-4">
            <Field :label="__('Type')" id="payload_type" :error="form.errors.payload_type">
                <select id="payload_type" v-model="form.payload_type" class="input-text">
                    <option value="raw_json">{{ __('Raw JSON template') }}</option>
                    <option value="mapped">{{ __('Mapped object') }}</option>
                    <option value="form">{{ __('Form encoded') }}</option>
                </select>
            </Field>

            <Field :label="__('Body source')" id="body_source"
                   :instructions="__('Use an inline template, or pick a reusable template from the library. Library templates win when both are set.')">
                <div class="flex gap-4">
                    <label class="flex items-center gap-2">
                        <input type="radio" name="body_source" value="inline"
                               :checked="bodySource === 'inline'"
                               @change="onBodySourceChange('inline')" />
                        <span>{{ __('Inline template') }}</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" name="body_source" value="library"
                               :checked="bodySource === 'library'"
                               :disabled="!Object.keys(availableTemplates).length"
                               @change="onBodySourceChange('library')" />
                        <span>{{ __('Library template') }}</span>
                    </label>
                </div>
            </Field>

            <Field v-if="bodySource === 'library'" :label="__('Library template')" id="payload_template_handle"
                   :error="form.errors.payload_template_handle"
                   :instructions="!Object.keys(availableTemplates).length
                       ? __('No outbound-body templates yet. Create one under Webhooks → Templates first.')
                       : __('Pick a reusable outbound-body template. The renderer will load and render it on each delivery.')">
                <select id="payload_template_handle" v-model="form.payload_template_handle" class="input-text">
                    <option value="">{{ __('— Pick a template —') }}</option>
                    <option v-for="(label, handle) in availableTemplates" :key="handle" :value="handle">{{ label }}</option>
                </select>
            </Field>

            <Field v-else :label="__('Template')" id="payload_template" :error="form.errors.payload_template"
                   :instructions="__('Use tokens like {{ entry:title }}, {{ system:timestamp_iso }}.')">
                <textarea id="payload_template" v-model="form.payload_template" rows="10"
                          class="input-text w-full font-mono text-sm"></textarea>
            </Field>
        </div>
    </Panel>

    <Panel :heading="__('Delivery')" class="mb-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4">
            <Field :label="__('Queue')" id="queue_enabled" :inline="true">
                <label class="flex items-center gap-2">
                    <input type="checkbox" v-model="form.queue_enabled" />
                    <span>{{ __('Send asynchronously via the queue') }}</span>
                </label>
            </Field>
            <Field :label="__('Body logging')" id="log_body_mode">
                <select id="log_body_mode" v-model="form.log_body_mode" class="input-text">
                    <option value="full">{{ __('Full') }}</option>
                    <option value="partial">{{ __('Partial') }}</option>
                    <option value="none">{{ __('None') }}</option>
                </select>
            </Field>
        </div>
    </Panel>

    <div v-if="!isNew" class="mt-8 flex justify-between">
        <Button variant="danger" @click="showDelete = true">{{ __('Delete') }}</Button>
        <Button variant="primary" :loading="form.processing" @click="save">{{ __('Save') }}</Button>
    </div>

    <ConfirmationModal
        v-if="!isNew"
        :open="showDelete"
        :title="__('Delete webhook')"
        :body-text="__('This permanently removes the webhook configuration. Past deliveries are kept.')"
        :button-text="__('Delete')"
        :danger="true"
        @confirm="destroy"
        @update:open="showDelete = $event"
    />
</template>
