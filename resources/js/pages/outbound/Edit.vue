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
    RadioGroup,
    Radio,
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
 * Outbound webhook edit/create page.
 *
 * Layout follows Statamic's PublishForm convention but stays declarative
 * here (we don't wrap a Blueprint yet — that's the next step). The
 * configuration is split into native <Tabs> so users no longer have to
 * scroll vertically through six Panel blocks.
 *
 * Auth secrets are write-only on the wire: PHP exposes only an
 * `auth_configured` flag. Submitting an empty `auth_config_json` keeps
 * the stored secret untouched (see OutboundController::normalizeAuthConfig).
 */
const props = defineProps({
    webhook: { type: Object, required: true },
    triggerOptions: { type: Object, required: true },
    authOptions: { type: Object, required: true },
    methodOptions: { type: Array, default: () => ['POST', 'GET', 'PUT', 'PATCH', 'DELETE'] },
    payloadTypeOptions: { type: Object, default: () => ({}) },
    logBodyModeOptions: { type: Object, default: () => ({}) },
    availableTemplates: { type: Object, default: () => ({}) },
    isNew: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
    canTest: { type: Boolean, default: false },
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

// Body source picker: inline textarea vs. picking an existing
// outbound-body Template. Persisting both is allowed; the renderer
// prefers the library entry (HttpRequestFactory::buildBody).
const bodySource = ref(form.payload_template_handle ? 'library' : 'inline');
watch(bodySource, value => {
    if (value === 'inline') form.payload_template_handle = '';
});

const activeTab = ref('general');
const showDelete = ref(false);
const testing = ref(false);
const testResult = ref(null);

const pageTitle = computed(() =>
    props.isNew ? __('Create outbound webhook') : (props.webhook.name || __('Outbound webhook'))
);
const saveLabel = computed(() => props.isNew ? __('Create') : __('Save'));
const hasLibraryTemplates = computed(() => Object.keys(props.availableTemplates).length > 0);

// Surface server-side validation errors on the right tab — without it
// users on the Identity tab miss errors on Trigger or Auth.
const tabsWithErrors = computed(() => {
    const map = {
        general: ['name', 'handle', 'description', 'enabled'],
        trigger: ['trigger_type', 'trigger_config'],
        request: ['url', 'method', 'timeout_seconds', 'follow_redirects'],
        auth: ['auth_type', 'auth_config_json', 'auth_config'],
        payload: ['payload_type', 'payload_template', 'payload_template_handle'],
        delivery: ['queue_enabled', 'log_body_mode', 'retry_strategy'],
    };
    const tabs = new Set();
    for (const [tab, keys] of Object.entries(map)) {
        if (keys.some(k => form.errors[k])) tabs.add(tab);
    }
    return tabs;
});

watch(() => form.hasErrors, hasErrors => {
    if (!hasErrors) return;
    const firstTabWithError = ['general', 'trigger', 'request', 'auth', 'payload', 'delivery']
        .find(t => tabsWithErrors.value.has(t));
    if (firstTabWithError) activeTab.value = firstTabWithError;
});

function save() {
    const verb = props.isNew ? 'post' : 'patch';
    form[verb](props.saveUrl, { preserveScroll: true });
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

const authPlaceholder = computed(() => {
    switch (form.auth_type) {
        case 'bearer': return '{ "token": "your-bearer-token" }';
        case 'basic':  return '{ "username": "user", "password": "pass" }';
        case 'header': return '{ "header": "X-Api-Key", "value": "your-secret" }';
        case 'hmac':   return '{ "secret": "your-shared-secret" }';
        default:       return '{ "secret": "your-secret" }';
    }
});

const authInstructions = computed(() => {
    if (props.webhook.auth_configured) {
        return __('A secret is already configured. Leave blank to keep it. Paste new JSON to replace — the value is encrypted at rest.');
    }
    return __('Stored encrypted. Format depends on the auth type — see the placeholder for an example.');
});
</script>

<template>
    <Head :title="[pageTitle, __('Outbound'), __('Webhook Manager')]" />

    <div class="max-w-5xl 3xl:max-w-6xl mx-auto" data-max-width-wrapper>
        <Header :title="pageTitle" icon="outgoing">
            <template v-if="!isNew" #subtitle>
                <StatusIndicator :status="webhook.enabled ? 'published' : 'draft'" />
                <Badge
                    :color="webhook.enabled ? 'green' : 'gray'"
                    :text="webhook.enabled ? __('Active') : __('Disabled')"
                />
            </template>

            <Button
                v-if="!isNew && canTest && testUrl"
                :loading="testing"
                :text="__('Test')"
                icon="paper-airplane"
                @click="runTest"
            />
            <Button
                variant="primary"
                :loading="form.processing"
                :text="saveLabel"
                @click="save"
            />

            <CommandPaletteItem
                v-if="!isNew && canTest && testUrl"
                category="Actions"
                :text="__('Test webhook')"
                icon="paper-airplane"
                :action="runTest"
            />
            <CommandPaletteItem
                v-if="!isNew && canDelete && deleteUrl"
                category="Actions"
                :text="__('Delete webhook')"
                icon="trash"
                :action="() => (showDelete = true)"
            />
        </Header>

        <Alert
            v-if="testResult"
            :variant="testResult.ok ? 'success' : 'error'"
            :heading="testResult.ok ? __('Test request succeeded') : __('Test request failed')"
            :text="`HTTP ${testResult.response_status ?? '—'} — ${testResult.duration_ms ?? '?'}ms${testResult.error_message ? ' — ' + testResult.error_message : ''}`"
            class="mb-4"
        />

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
                <TabTrigger value="request">
                    {{ __('Request') }}
                    <Badge v-if="tabsWithErrors.has('request')" color="red" class="ms-1.5" :text="__('!')" />
                </TabTrigger>
                <TabTrigger value="auth">
                    {{ __('Authentication') }}
                    <Badge v-if="tabsWithErrors.has('auth')" color="red" class="ms-1.5" :text="__('!')" />
                </TabTrigger>
                <TabTrigger value="payload">
                    {{ __('Payload') }}
                    <Badge v-if="tabsWithErrors.has('payload')" color="red" class="ms-1.5" :text="__('!')" />
                </TabTrigger>
                <TabTrigger value="delivery">
                    {{ __('Delivery') }}
                    <Badge v-if="tabsWithErrors.has('delivery')" color="red" class="ms-1.5" :text="__('!')" />
                </TabTrigger>
            </TabList>

            <!-- ───────── General ───────── -->
            <TabContent value="general">
                <Panel class="mt-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
                        <Field
                            :label="__('Name')"
                            id="name"
                            :required="true"
                            :error="form.errors.name"
                            :instructions="__('Human-readable name shown across the CP.')"
                        >
                            <Input id="name" v-model="form.name" autofocus />
                        </Field>

                        <Field
                            :label="__('Handle')"
                            id="handle"
                            :required="true"
                            :error="form.errors.handle"
                            :instructions="__('Internal identifier. Lowercase, hyphens or underscores only.')"
                        >
                            <Input id="handle" v-model="form.handle" pattern="[a-z0-9_-]+" />
                        </Field>

                        <Field
                            :label="__('Description')"
                            id="description"
                            class="md:col-span-2"
                            :error="form.errors.description"
                        >
                            <Textarea id="description" v-model="form.description" :rows="2" />
                        </Field>

                        <Field
                            :label="__('Status')"
                            id="enabled"
                            class="md:col-span-2"
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
                    <div class="grid grid-cols-1 gap-6 p-6">
                        <Field
                            :label="__('Trigger type')"
                            id="trigger_type"
                            :required="true"
                            :error="form.errors.trigger_type"
                            :instructions="__('Internal event that fires this webhook.')"
                        >
                            <Select id="trigger_type" v-model="form.trigger_type">
                                <option v-for="(label, value) in triggerOptions" :key="value" :value="value">
                                    {{ label }}
                                </option>
                            </Select>
                        </Field>
                    </div>
                </Panel>
            </TabContent>

            <!-- ───────── Request ───────── -->
            <TabContent value="request">
                <Panel class="mt-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
                        <Field
                            :label="__('URL')"
                            id="url"
                            :required="true"
                            class="md:col-span-2"
                            :error="form.errors.url"
                            :instructions="__('The destination URL the webhook will hit on each delivery.')"
                        >
                            <Input id="url" v-model="form.url" type="url" placeholder="https://example.com/hooks/incoming" />
                        </Field>

                        <Field
                            :label="__('Method')"
                            id="method"
                            :error="form.errors.method"
                        >
                            <Select id="method" v-model="form.method">
                                <option v-for="m in methodOptions" :key="m" :value="m">{{ m }}</option>
                            </Select>
                        </Field>

                        <Field
                            :label="__('Timeout (seconds)')"
                            id="timeout_seconds"
                            :error="form.errors.timeout_seconds"
                            :instructions="__('Hard cap per request, including TLS + redirects.')"
                        >
                            <Input id="timeout_seconds" v-model.number="form.timeout_seconds" type="number" min="1" max="120" />
                        </Field>

                        <Field
                            :label="__('Follow redirects')"
                            id="follow_redirects"
                            class="md:col-span-2"
                            :error="form.errors.follow_redirects"
                        >
                            <Switch
                                id="follow_redirects"
                                v-model="form.follow_redirects"
                                :text="__('Follow HTTP redirects')"
                            />
                        </Field>
                    </div>
                </Panel>
            </TabContent>

            <!-- ───────── Authentication ───────── -->
            <TabContent value="auth">
                <Panel class="mt-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
                        <Field
                            :label="__('Type')"
                            id="auth_type"
                            :error="form.errors.auth_type"
                        >
                            <Select id="auth_type" v-model="form.auth_type">
                                <option v-for="(label, value) in authOptions" :key="value" :value="value">{{ label }}</option>
                            </Select>
                        </Field>

                        <div v-if="webhook.auth_configured" class="md:col-span-2">
                            <Alert
                                variant="info"
                                :heading="__('Secret already configured')"
                                :text="__('Leave the field below empty to keep the existing encrypted secret.')"
                            />
                        </div>

                        <Field
                            v-if="form.auth_type !== 'none'"
                            :label="__('Auth config (JSON)')"
                            id="auth_config_json"
                            class="md:col-span-2"
                            :error="form.errors.auth_config_json"
                            :instructions="authInstructions"
                        >
                            <CodeEditor
                                id="auth_config_json"
                                v-model="form.auth_config_json"
                                mode="json"
                                :placeholder="authPlaceholder"
                                :rows="4"
                            />
                        </Field>
                    </div>
                </Panel>
            </TabContent>

            <!-- ───────── Payload ───────── -->
            <TabContent value="payload">
                <Panel class="mt-4">
                    <div class="grid grid-cols-1 gap-6 p-6">
                        <Field
                            :label="__('Type')"
                            id="payload_type"
                            :error="form.errors.payload_type"
                        >
                            <Select id="payload_type" v-model="form.payload_type">
                                <option
                                    v-for="(label, value) in (Object.keys(payloadTypeOptions).length ? payloadTypeOptions : { raw_json: __('Raw JSON template'), mapped: __('Mapped object'), form: __('Form encoded') })"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </option>
                            </Select>
                        </Field>

                        <Field
                            :label="__('Body source')"
                            id="body_source"
                            :instructions="__('Use an inline template, or pick a reusable template from the library. Library templates win when both are set.')"
                        >
                            <RadioGroup v-model="bodySource" id="body_source">
                                <Radio value="inline" :label="__('Inline template')" />
                                <Radio
                                    value="library"
                                    :label="__('Library template')"
                                    :disabled="!hasLibraryTemplates"
                                />
                            </RadioGroup>
                        </Field>

                        <Field
                            v-if="bodySource === 'library'"
                            :label="__('Library template')"
                            id="payload_template_handle"
                            :error="form.errors.payload_template_handle"
                            :instructions="hasLibraryTemplates
                                ? __('Pick a reusable outbound-body template. The renderer loads + renders it on each delivery.')
                                : __('No outbound-body templates yet. Create one under Webhook Manager → Templates first.')"
                        >
                            <Select
                                id="payload_template_handle"
                                v-model="form.payload_template_handle"
                                :disabled="!hasLibraryTemplates"
                            >
                                <option value="">{{ __('— Pick a template —') }}</option>
                                <option v-for="(label, handle) in availableTemplates" :key="handle" :value="handle">
                                    {{ label }}
                                </option>
                            </Select>
                        </Field>

                        <Field
                            v-else
                            :label="__('Template')"
                            id="payload_template"
                            :error="form.errors.payload_template"
                            :instructions="__('Use tokens like {{ entry:title }} or {{ system:timestamp_iso }}.')"
                        >
                            <CodeEditor
                                id="payload_template"
                                v-model="form.payload_template"
                                :mode="form.payload_type === 'raw_json' ? 'json' : 'text'"
                                :rows="12"
                            />
                        </Field>
                    </div>
                </Panel>
            </TabContent>

            <!-- ───────── Delivery ───────── -->
            <TabContent value="delivery">
                <Panel class="mt-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
                        <Field
                            :label="__('Queue')"
                            id="queue_enabled"
                            class="md:col-span-2"
                            :error="form.errors.queue_enabled"
                            :instructions="__('Recommended. Synchronous delivery only when latency is critical.')"
                        >
                            <Switch
                                id="queue_enabled"
                                v-model="form.queue_enabled"
                                :text="__('Send asynchronously via the queue')"
                            />
                        </Field>

                        <Field
                            :label="__('Body logging')"
                            id="log_body_mode"
                            :error="form.errors.log_body_mode"
                            :instructions="__('Controls how much of request/response bodies is persisted to delivery logs.')"
                        >
                            <Select id="log_body_mode" v-model="form.log_body_mode">
                                <option
                                    v-for="(label, value) in (Object.keys(logBodyModeOptions).length ? logBodyModeOptions : { full: __('Full'), partial: __('Partial'), none: __('None') })"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </option>
                            </Select>
                        </Field>
                    </div>
                </Panel>
            </TabContent>
        </Tabs>

        <div v-if="!isNew && canDelete" class="mt-8 flex justify-between items-center">
            <Button variant="danger" :text="__('Delete webhook')" @click="showDelete = true" />
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
            :title="__('Delete webhook')"
            :body-text="__('This permanently removes the webhook configuration. Past deliveries are kept.')"
            :button-text="__('Delete')"
            :danger="true"
            @confirm="destroy"
            @update:open="showDelete = $event"
        />
    </div>
</template>
