<script setup>
import axios from 'axios';
import { ref, computed, watch } from 'vue';
import { Head } from '@statamic/cms/inertia';
import { useForm, router } from '@statamic/cms/inertia';
import {
    Card,
    Header,
    Button,
    Badge,
    Dropdown,
    DropdownItem,
    DropdownMenu,
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
    props.isNew
        ? __('webhook-manager::messages.cp.edit_title_new')
        : (props.webhook.name || __('webhook-manager::messages.cp.edit_title_fallback'))
);
const saveLabel = computed(() => props.isNew ? __('webhook-manager::messages.cp.btn_create') : __('webhook-manager::messages.cp.btn_save'));
const hasLibraryTemplates = computed(() => Object.keys(props.availableTemplates).length > 0);

/**
 * Statamic's <Select> wraps <Combobox>, which expects `:options` as an
 * Array of `{ value, label }` objects — NOT nested HTML <option> tags.
 * Passing a `{ key: label }` Object (the shape PHP returns from
 * Registry::options()) trips an internal `null.find()` once a tab swaps
 * in. The computed wrappers below normalise everything we pass to
 * <Select :options> into the Array shape Combobox expects.
 */
function objectToOptions(obj) {
    if (!obj || typeof obj !== 'object') return [];
    return Object.entries(obj).map(([value, label]) => ({ value, label }));
}

const triggerOptionsArray = computed(() => objectToOptions(props.triggerOptions));
const authOptionsArray = computed(() => objectToOptions(props.authOptions));
const methodOptionsArray = computed(() =>
    (props.methodOptions || []).map(m => ({ value: m, label: m }))
);
const payloadTypeOptionsArray = computed(() => {
    const opts = Object.keys(props.payloadTypeOptions).length
        ? props.payloadTypeOptions
        : {
            raw_json: __('webhook-manager::messages.cp.payload_raw_json'),
            mapped: __('webhook-manager::messages.cp.payload_mapped'),
            form: __('webhook-manager::messages.cp.payload_form'),
        };
    return objectToOptions(opts);
});
const logBodyModeOptionsArray = computed(() => {
    const opts = Object.keys(props.logBodyModeOptions).length
        ? props.logBodyModeOptions
        : {
            full: __('webhook-manager::messages.cp.log_full'),
            partial: __('webhook-manager::messages.cp.log_partial'),
            none: __('webhook-manager::messages.cp.log_none'),
        };
    return objectToOptions(opts);
});
const availableTemplatesArray = computed(() => [
    { value: '', label: __('webhook-manager::messages.cp.template_pick') },
    ...objectToOptions(props.availableTemplates),
]);

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

// Rejections that reach this page outside `form.submit()` — a refused delete.
// useForm's bag only carries what the form itself sent.
const actionErrors = ref({});

// Everything the server said, from either source. SaveOutboundWebhookRequest
// validates eight keys this page has no input for (`headers`, `conditions`,
// `retry_strategy.*`, `success_matcher`, `idempotency_enabled`, …); without
// this banner their rejection had nowhere to go and was shown nowhere.
const allErrors = computed(() => ({ ...form.errors, ...actionErrors.value }));

function save() {
    // Defensive: never call form.post(undefined). Inertia's visit() crashes
    // with "Cannot read properties of undefined (reading 'url')" inside the
    // minified bundle when the URL prop is missing — log loudly so the
    // problem is obvious in the browser console rather than as a silent
    // visit() crash on click.
    if (!props.saveUrl) {
        console.error(
            '[webhook-manager] Outbound/Edit: saveUrl prop is missing — cannot submit.',
            'Inertia props received:', { isNew: props.isNew, saveUrl: props.saveUrl, indexUrl: props.indexUrl }
        );
        return;
    }
    const verb = props.isNew ? 'post' : 'patch';
    // Use form.submit(method, url, options) over form[verb](url, options).
    // Same call internally, but more explicit and avoids the dynamic-key
    // lookup that has tripped up some Inertia v2 versions.
    form.submit(verb, props.saveUrl, { preserveScroll: true });
}

async function runTest() {
    if (!props.testUrl) return;
    testing.value = true;
    testResult.value = null;
    try {
        const res = await axios.post(props.testUrl, { sample_payload: {} });
        testResult.value = res.data;
    } catch (e) {
        testResult.value = { ok: false, error_message: e?.response?.data?.message ?? e.message };
    } finally {
        testing.value = false;
    }
}

function destroy() {
    if (!props.deleteUrl) {
        console.error('[webhook-manager] Outbound/Edit: deleteUrl prop is missing — cannot delete.');
        return;
    }
    router.delete(props.deleteUrl, {
        preserveScroll: true,
        onError: (errors) => { actionErrors.value = errors || {}; showDelete.value = false; },
        onSuccess: () => { actionErrors.value = {}; showDelete.value = false; },
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
        return __('webhook-manager::messages.cp.auth_hint_configured');
    }

    return __('webhook-manager::messages.cp.auth_hint_new');
});
</script>

<template>
    <Head :title="[pageTitle, __('webhook-manager::messages.cp.webhooks_heading'), __('webhook-manager::messages.cp.app_name')]" />

    <!-- The narrow detail container ui-vocabulary §2.3 sanctions for detail
         and settings screens; `data-max-width-wrapper` is what lets the
         header's expand-layout toggle still widen it. -->
    <div class="max-w-5xl 3xl:max-w-6xl mx-auto" data-max-width-wrapper>
        <Header icon="arrow-up-right">
            <!-- The status used to sit in a `#subtitle` slot. `Header` has no
                 such slot (only `title` and `actions`), so it rendered
                 nothing — silently, the way an unknown slot always does. -->
            <template #title>
                <span class="flex flex-wrap items-center gap-3">
                    {{ pageTitle }}
                    <template v-if="!isNew">
                        <StatusIndicator :status="webhook.enabled ? 'published' : 'draft'" />
                        <Badge
                            :color="webhook.enabled ? 'green' : 'default'"
                            :text="webhook.enabled ? __('webhook-manager::messages.cp.status_active') : __('webhook-manager::messages.cp.status_disabled')"
                        />
                    </template>
                </span>
            </template>

            <!-- Header order is core's: the `…` dropdown first, the primary
                 action last (ui-vocabulary §24). Delete used to be a
                 `Button variant="danger"` in a footer bar under the tabs;
                 core uses `danger` only for the confirm button inside a
                 modal, and a destructive page action is a `DropdownItem
                 variant="destructive"`. The footer's second Save duplicated
                 the header's and went with it. -->
            <Dropdown v-if="!isNew && canDelete && deleteUrl">
                <DropdownMenu>
                    <DropdownItem
                        variant="destructive"
                        icon="trash"
                        :text="__('webhook-manager::messages.cp.delete_webhook')"
                        @click="showDelete = true"
                    />
                </DropdownMenu>
            </Dropdown>
            <Button
                v-if="!isNew && canTest && testUrl"
                :loading="testing"
                :text="__('webhook-manager::messages.cp.action_test')"
                icon="arrow-up-right"
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
                :text="__('webhook-manager::messages.cp.test_webhook')"
                icon="arrow-up-right"
                :action="runTest"
            />
            <CommandPaletteItem
                v-if="!isNew && canDelete && deleteUrl"
                category="Actions"
                :text="__('webhook-manager::messages.cp.delete_webhook')"
                icon="trash"
                :action="() => (showDelete = true)"
            />
        </Header>

        <!-- Everything the server rejected, above the mask, before the tabs. -->
        <Alert
            v-if="Object.keys(allErrors).length"
            variant="error"
            class="mb-4"
            data-webhook-form-errors
        >
            <ul class="list-disc list-inside space-y-0.5">
                <li v-for="(err, key) in allErrors" :key="key">{{ err }}</li>
            </ul>
        </Alert>

        <Alert
            v-if="testResult"
            :variant="testResult.ok ? 'success' : 'error'"
            :heading="testResult.ok ? __('webhook-manager::messages.cp.test_ok') : __('webhook-manager::messages.cp.test_failed')"
            :text="`HTTP ${testResult.response_status ?? '—'} — ${testResult.duration_ms ?? '?'}ms${testResult.error_message ? ' — ' + testResult.error_message : ''}`"
            class="mb-4"
        />

        <Tabs v-model="activeTab" class="mt-4">
            <TabList>
                <TabTrigger value="general">
                    {{ __('webhook-manager::messages.cp.tab_general') }}
                    <!-- `__('!')` was a translation key made of a single
                         exclamation mark. The marker is not text to translate;
                         the wording belongs on the aria-label. -->
                    <Badge
                        v-if="tabsWithErrors.has('general')"
                        color="red"
                        class="ms-1.5"
                        text="!"
                        :aria-label="__('webhook-manager::messages.cp.tab_has_errors')"
                    />
                </TabTrigger>
                <TabTrigger value="trigger">
                    {{ __('webhook-manager::messages.cp.tab_trigger') }}
                    <!-- `__('!')` was a translation key made of a single
                         exclamation mark. The marker is not text to translate;
                         the wording belongs on the aria-label. -->
                    <Badge
                        v-if="tabsWithErrors.has('trigger')"
                        color="red"
                        class="ms-1.5"
                        text="!"
                        :aria-label="__('webhook-manager::messages.cp.tab_has_errors')"
                    />
                </TabTrigger>
                <TabTrigger value="request">
                    {{ __('webhook-manager::messages.cp.tab_request') }}
                    <!-- `__('!')` was a translation key made of a single
                         exclamation mark. The marker is not text to translate;
                         the wording belongs on the aria-label. -->
                    <Badge
                        v-if="tabsWithErrors.has('request')"
                        color="red"
                        class="ms-1.5"
                        text="!"
                        :aria-label="__('webhook-manager::messages.cp.tab_has_errors')"
                    />
                </TabTrigger>
                <TabTrigger value="auth">
                    {{ __('webhook-manager::messages.cp.tab_auth') }}
                    <!-- `__('!')` was a translation key made of a single
                         exclamation mark. The marker is not text to translate;
                         the wording belongs on the aria-label. -->
                    <Badge
                        v-if="tabsWithErrors.has('auth')"
                        color="red"
                        class="ms-1.5"
                        text="!"
                        :aria-label="__('webhook-manager::messages.cp.tab_has_errors')"
                    />
                </TabTrigger>
                <TabTrigger value="payload">
                    {{ __('webhook-manager::messages.cp.tab_payload') }}
                    <!-- `__('!')` was a translation key made of a single
                         exclamation mark. The marker is not text to translate;
                         the wording belongs on the aria-label. -->
                    <Badge
                        v-if="tabsWithErrors.has('payload')"
                        color="red"
                        class="ms-1.5"
                        text="!"
                        :aria-label="__('webhook-manager::messages.cp.tab_has_errors')"
                    />
                </TabTrigger>
                <TabTrigger value="delivery">
                    {{ __('webhook-manager::messages.cp.tab_delivery') }}
                    <!-- `__('!')` was a translation key made of a single
                         exclamation mark. The marker is not text to translate;
                         the wording belongs on the aria-label. -->
                    <Badge
                        v-if="tabsWithErrors.has('delivery')"
                        color="red"
                        class="ms-1.5"
                        text="!"
                        :aria-label="__('webhook-manager::messages.cp.tab_has_errors')"
                    />
                </TabTrigger>
            </TabList>

            <!-- ───────── General ───────── -->
            <TabContent value="general">
                <Panel class="mt-4">
                    <Card class="space-y-6">
                        <Field inline
                            :label="__('webhook-manager::messages.cp.field_name')"
                            id="name"
                            :required="true"
                            :error="form.errors.name"
                            :instructions="__('webhook-manager::messages.cp.field_name_hint')"
                        >
                            <Input id="name" v-model="form.name" autofocus />
                        </Field>

                        <Field inline
                            :label="__('webhook-manager::messages.cp.field_handle')"
                            id="handle"
                            :required="true"
                            :error="form.errors.handle"
                            :instructions="__('webhook-manager::messages.cp.field_handle_hint')"
                        >
                            <Input id="handle" v-model="form.handle" pattern="[a-z0-9_-]+" />
                        </Field>

                        <Field inline
                            :label="__('webhook-manager::messages.cp.field_description')"
                            id="description"
                            :error="form.errors.description"
                        >
                            <Textarea id="description" v-model="form.description" :rows="2" />
                        </Field>

                        <Field inline
                            :label="__('webhook-manager::messages.cp.field_status')"
                            id="enabled"
                            :error="form.errors.enabled"
                        >
                            <Switch
                                id="enabled"
                                v-model="form.enabled"
                                :text="form.enabled ? __('webhook-manager::messages.cp.field_status_on') : __('webhook-manager::messages.cp.field_status_off')"
                            />
                        </Field>
                    </Card>
                </Panel>
            </TabContent>

            <!-- ───────── Trigger ───────── -->
            <TabContent value="trigger">
                <Panel class="mt-4">
                    <Card class="space-y-6">
                        <Field inline
                            :label="__('webhook-manager::messages.cp.field_trigger_type')"
                            id="trigger_type"
                            :required="true"
                            :error="form.errors.trigger_type"
                            :instructions="__('webhook-manager::messages.cp.field_trigger_type_hint')"
                        >
                            <Select id="trigger_type" v-model="form.trigger_type" :options="triggerOptionsArray" />
                        </Field>
                    </Card>
                </Panel>
            </TabContent>

            <!-- ───────── Request ───────── -->
            <TabContent value="request">
                <Panel class="mt-4">
                    <Card class="space-y-6">
                        <Field inline
                            :label="__('webhook-manager::messages.cp.field_url')"
                            id="url"
                            :required="true"
                            :error="form.errors.url"
                            :instructions="__('webhook-manager::messages.cp.field_url_hint')"
                        >
                            <Input id="url" v-model="form.url" type="url" placeholder="https://example.com/hooks/incoming" />
                        </Field>

                        <Field inline
                            :label="__('webhook-manager::messages.cp.field_method')"
                            id="method"
                            :error="form.errors.method"
                        >
                            <Select id="method" v-model="form.method" :options="methodOptionsArray" />
                        </Field>

                        <Field inline
                            :label="__('webhook-manager::messages.cp.field_timeout')"
                            id="timeout_seconds"
                            :error="form.errors.timeout_seconds"
                            :instructions="__('webhook-manager::messages.cp.field_timeout_hint')"
                        >
                            <Input id="timeout_seconds" v-model.number="form.timeout_seconds" type="number" min="1" max="120" />
                        </Field>

                        <Field inline
                            :label="__('webhook-manager::messages.cp.field_follow_redirects')"
                            id="follow_redirects"
                            :error="form.errors.follow_redirects"
                        >
                            <Switch
                                id="follow_redirects"
                                v-model="form.follow_redirects"
                                :text="__('webhook-manager::messages.cp.field_follow_redirects_text')"
                            />
                        </Field>
                    </Card>
                </Panel>
            </TabContent>

            <!-- ───────── Authentication ───────── -->
            <TabContent value="auth">
                <Panel class="mt-4">
                    <Card class="space-y-6">
                        <Field inline
                            :label="__('webhook-manager::messages.cp.field_auth_type')"
                            id="auth_type"
                            :error="form.errors.auth_type"
                        >
                            <Select id="auth_type" v-model="form.auth_type" :options="authOptionsArray" />
                        </Field>

                        <div v-if="webhook.auth_configured" class="md:col-span-2">
                            <!-- `warning`, not the `info` that was here — and
                                 `info` is not an Alert variant at all
                                 (default/warning/error/success), so this
                                 rendered neutral. It is the one banner on this
                                 page that guards against a destructive
                                 mistake: the field below already holds an
                                 encrypted secret, and anything typed into it
                                 replaces that secret for good. -->
                            <Alert
                                variant="warning"
                                :heading="__('webhook-manager::messages.cp.auth_secret_set_heading')"
                                :text="__('webhook-manager::messages.cp.auth_secret_set_text')"
                            />
                        </div>

                        <Field inline
                            v-if="form.auth_type !== 'none'"
                            :label="__('webhook-manager::messages.cp.field_auth_config')"
                            id="auth_config_json"
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
                    </Card>
                </Panel>
            </TabContent>

            <!-- ───────── Payload ───────── -->
            <TabContent value="payload">
                <Panel class="mt-4">
                    <Card class="space-y-6">
                        <Field inline
                            :label="__('webhook-manager::messages.cp.field_payload_type')"
                            id="payload_type"
                            :error="form.errors.payload_type"
                        >
                            <Select id="payload_type" v-model="form.payload_type" :options="payloadTypeOptionsArray" />
                        </Field>

                        <Field inline
                            :label="__('webhook-manager::messages.cp.field_body_source')"
                            id="body_source"
                            :instructions="__('webhook-manager::messages.cp.field_body_source_hint')"
                        >
                            <RadioGroup v-model="bodySource" id="body_source">
                                <Radio value="inline" :label="__('webhook-manager::messages.cp.body_source_inline')" />
                                <Radio
                                    value="library"
                                    :label="__('webhook-manager::messages.cp.body_source_library')"
                                    :disabled="!hasLibraryTemplates"
                                />
                            </RadioGroup>
                        </Field>

                        <Field inline
                            v-if="bodySource === 'library'"
                            :label="__('webhook-manager::messages.cp.field_library_template')"
                            id="payload_template_handle"
                            :error="form.errors.payload_template_handle"
                            :instructions="hasLibraryTemplates
                                ? __('webhook-manager::messages.cp.field_library_template_hint')
                                : __('webhook-manager::messages.cp.field_library_template_empty')"
                        >
                            <Select
                                id="payload_template_handle"
                                v-model="form.payload_template_handle"
                                :options="availableTemplatesArray"
                                :disabled="!hasLibraryTemplates"
                            />
                        </Field>

                        <Field inline
                            v-else
                            :label="__('webhook-manager::messages.cp.field_template')"
                            id="payload_template"
                            :error="form.errors.payload_template"
                            :instructions="__('webhook-manager::messages.cp.field_template_hint')"
                        >
                            <CodeEditor
                                id="payload_template"
                                v-model="form.payload_template"
                                :mode="form.payload_type === 'raw_json' ? 'json' : 'text'"
                                :rows="12"
                            />
                        </Field>
                    </Card>
                </Panel>
            </TabContent>

            <!-- ───────── Delivery ───────── -->
            <TabContent value="delivery">
                <Panel class="mt-4">
                    <Card class="space-y-6">
                        <Field inline
                            :label="__('webhook-manager::messages.cp.field_queue')"
                            id="queue_enabled"
                            :error="form.errors.queue_enabled"
                            :instructions="__('webhook-manager::messages.cp.field_queue_hint')"
                        >
                            <Switch
                                id="queue_enabled"
                                v-model="form.queue_enabled"
                                :text="__('webhook-manager::messages.cp.field_queue_text')"
                            />
                        </Field>

                        <Field inline
                            :label="__('webhook-manager::messages.cp.field_log_body')"
                            id="log_body_mode"
                            :error="form.errors.log_body_mode"
                            :instructions="__('webhook-manager::messages.cp.field_log_body_hint')"
                        >
                            <Select id="log_body_mode" v-model="form.log_body_mode" :options="logBodyModeOptionsArray" />
                        </Field>
                    </Card>
                </Panel>
            </TabContent>
        </Tabs>

        <ConfirmationModal
            v-if="!isNew && deleteUrl"
            :open="showDelete"
            :title="__('webhook-manager::messages.cp.delete_webhook')"
            :body-text="__('webhook-manager::messages.cp.delete_webhook_confirm')"
            :button-text="__('webhook-manager::messages.cp.delete')"
            :danger="true"
            @confirm="destroy"
            @update:open="showDelete = $event"
        />
    </div>
</template>
