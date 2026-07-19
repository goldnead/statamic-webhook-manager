<script setup>
import axios from 'axios';
import { ref, computed, watch } from 'vue';
import { Head } from '@statamic/cms/inertia';
import { useForm, router } from '@statamic/cms/inertia';
import {
    Header,
    Button,
    Badge,
    Alert,
    Field,
    Input,
    Textarea,
    Select,
    CodeEditor,
    Tabs,
    TabList,
    TabTrigger,
    TabContent,
    Panel,
    CardPanel,
    ConfirmationModal,
    CommandPaletteItem,
} from '@statamic/cms/ui';

/**
 * Template edit/create page.
 *
 * Layout follows the Outbound/Edit pilot pattern:
 *   Header + useForm (no Blueprint yet) + Tabs (General / Body / Preview)
 *
 * The Preview tab preserves the full preview workflow from the legacy
 * Edit.vue (axios POST to previewUrl, renders result + issues) but
 * migrated into the new layout.
 */
const props = defineProps({
    template:   { type: Object, required: true },
    typeOptions: { type: Object, required: true },
    namespaces:  { type: Array,  default: () => [] },
    isNew:       { type: Boolean, default: false },
    canDelete:   { type: Boolean, default: false },
    saveUrl:     { type: String, required: true },
    deleteUrl:   { type: String, default: null },
    previewUrl:  { type: String, required: true },
    indexUrl:    { type: String, required: true },
});

// ── Form state ────────────────────────────────────────────────────────
const form = useForm({
    name:   props.template.name   ?? '',
    handle: props.template.handle ?? '',
    type:   props.template.type   ?? 'outbound_body',
    body:   props.template.body   ?? '',
    meta:   props.template.meta   ?? null,
});

// ── UI state ──────────────────────────────────────────────────────────
const activeTab    = ref('general');
const showDelete   = ref(false);
const previewing   = ref(false);
const previewResult = ref(null);

const samplePayload = ref('{\n    "id": 1,\n    "title": "Sample entry",\n    "site": "default"\n}');
const sourceType    = ref('entry');

// Statamic's <Select> wraps <Combobox>, which expects `:options` as an
// Array of { value, label } objects — not nested HTML <option> tags.
const sourceTypeOptions = [
    { value: 'entry',      label: __('Entry') },
    { value: 'user',       label: __('User') },
    { value: 'term',       label: __('Term') },
    { value: 'asset',      label: __('Asset') },
    { value: 'collection', label: __('Collection') },
];

function objectToOptions(obj) {
    if (!obj || typeof obj !== 'object') return [];
    return Object.entries(obj).map(([value, label]) => ({ value, label }));
}

const typeOptionsArray = computed(() => objectToOptions(props.typeOptions));

// ── Computed ──────────────────────────────────────────────────────────
const pageTitle = computed(() =>
    props.isNew
        ? __('Create Template')
        : (props.template.name || __('Template'))
);

const saveLabel = computed(() => props.isNew ? __('Create') : __('Save'));

// CodeEditor mode: JSON makes sense for outbound_body when the content
// looks like raw JSON; for notification / inbound_response we use text
// (Twig/Antlers). Keep it simple — the user can always switch in the editor.
const editorMode = computed(() =>
    form.type === 'outbound_body' ? 'json' : 'text'
);

// Surface server-side validation errors on the correct tab — same
// pattern as Outbound/Edit so users don't miss errors on hidden tabs.
const tabsWithErrors = computed(() => {
    const map = {
        general: ['name', 'handle', 'type'],
        body:    ['body', 'meta'],
        preview: [],
    };
    const tabs = new Set();
    for (const [tab, keys] of Object.entries(map)) {
        if (keys.some(k => form.errors[k])) tabs.add(tab);
    }
    return tabs;
});

watch(() => form.hasErrors, hasErrors => {
    if (!hasErrors) return;
    const first = ['general', 'body', 'preview']
        .find(t => tabsWithErrors.value.has(t));
    if (first) activeTab.value = first;
});

// ── Actions ───────────────────────────────────────────────────────────
function save() {
    if (!props.saveUrl) {
        console.error(
            '[webhook-manager] Templates/Edit: saveUrl prop is missing — cannot submit.',
            'Inertia props received:', { isNew: props.isNew, saveUrl: props.saveUrl, indexUrl: props.indexUrl }
        );
        return;
    }
    const verb = props.isNew ? 'post' : 'patch';
    form.submit(verb, props.saveUrl, { preserveScroll: true });
}

async function runPreview() {
    previewing.value = true;
    previewResult.value = null;
    try {
        const payload = samplePayload.value.trim()
            ? JSON.parse(samplePayload.value)
            : {};
        const res = await axios.post(props.previewUrl, {
            template:       form.body,
            sample_payload: payload,
            source_type:    sourceType.value,
        });
        previewResult.value = res.data;
    } catch (e) {
        previewResult.value = {
            rendered: '',
            issues:   [e?.response?.data?.message ?? e?.message ?? __('Preview failed.')],
        };
    } finally {
        previewing.value = false;
    }
}

function destroy() {
    if (!props.deleteUrl) {
        console.error('[webhook-manager] Templates/Edit: deleteUrl prop is missing — cannot delete.');
        return;
    }
    router.delete(props.deleteUrl, {
        preserveScroll: true,
        onSuccess: () => { showDelete.value = false; },
    });
}

function copyToClipboard(text) {
    navigator.clipboard?.writeText(text);
}
</script>

<template>
    <Head :title="[pageTitle, __('Templates'), __('Webhook Manager')]" />

    <div class="max-w-5xl 3xl:max-w-6xl mx-auto" data-max-width-wrapper>

        <!-- ── Page header ─────────────────────────────────────────── -->
        <Header :title="pageTitle" icon="layout-grid">
            <Button
                :text="saveLabel"
                variant="primary"
                :loading="form.processing"
                @click="save"
            />
            <CommandPaletteItem
                v-if="canDelete && deleteUrl"
                category="Actions"
                :text="__('Delete Template')"
                icon="trash"
                danger
                @click="showDelete = true"
            />
        </Header>

        <!-- ── Contextual hint ────────────────────────────────────── -->
        <Alert variant="info" class="mb-6">
            {{ __('webhook-manager::messages.templates_edit_hint') }}
        </Alert>

        <!-- ── Global error banner ────────────────────────────────── -->
        <Alert
            v-if="form.hasErrors && Object.keys(form.errors).length"
            type="error"
            class="mb-6"
        >
            <ul class="list-disc list-inside">
                <li v-for="err in Object.values(form.errors)" :key="err">{{ err }}</li>
            </ul>
        </Alert>

        <!-- ── Tabs ───────────────────────────────────────────────── -->
        <Tabs v-model="activeTab">
            <TabList>
                <TabTrigger
                    value="general"
                    :label="__('General')"
                    :has-error="tabsWithErrors.has('general')"
                />
                <TabTrigger
                    value="body"
                    :label="__('Body')"
                    :has-error="tabsWithErrors.has('body')"
                />
                <TabTrigger
                    value="preview"
                    :label="__('Preview')"
                />
            </TabList>

            <!-- ── General tab ─────────────────────────────────────── -->
            <TabContent value="general">
                <Panel>
                    <div class="bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-gray-700/80 shadow-sm p-6 space-y-6">
                        <Field inline
                            :label="__('Name')"
                            :error="form.errors.name"
                            required
                        >
                            <Input
                                v-model="form.name"
                                type="text"
                                :placeholder="__('My template')"
                                :has-error="!!form.errors.name"
                            />
                        </Field>

                        <Field inline
                            :label="__('Handle')"
                            :error="form.errors.handle"
                            :instructions="__('Lowercase letters, numbers, underscores and hyphens only.')"
                            required
                        >
                            <Input
                                v-model="form.handle"
                                type="text"
                                :placeholder="__('my_template')"
                                :has-error="!!form.errors.handle"
                            />
                        </Field>

                        <Field inline
                            :label="__('Type')"
                            :error="form.errors.type"
                            required
                        >
                            <Select v-model="form.type" :options="typeOptionsArray" />
                        </Field>
                    </div>
                </Panel>
            </TabContent>

            <!-- ── Body tab ────────────────────────────────────────── -->
            <TabContent value="body">
                <Panel>
                    <div class="p-6 space-y-4">
                        <Field inline
                            :label="__('Body')"
                            :error="form.errors.body"
                            :instructions="__('Template body. Twig / Antlers syntax is supported for non-JSON types.')"
                            required
                        >
                            <CodeEditor
                                v-model="form.body"
                                :mode="editorMode"
                                class="min-h-80"
                                :has-error="!!form.errors.body"
                            />
                        </Field>

                        <!-- Available namespaces as a collapsible hint panel -->
                        <Panel
                            v-if="namespaces.length"
                            :heading="__('Available variables')"
                            collapsible
                            :collapsed="true"
                        >
                            <ul class="divide-y divide-gray-200 dark:divide-dark-900">
                                <li
                                    v-for="ns in namespaces"
                                    :key="ns"
                                    class="flex items-center justify-between px-4 py-2 text-sm font-mono"
                                >
                                    <span>{{ ns }}</span>
                                    <Button
                                        size="xs"
                                        variant="default"
                                        :text="__('Copy')"
                                        icon="duplicate"
                                        @click="copyToClipboard('{{ ' + ns + ' }}')"
                                    />
                                </li>
                            </ul>
                        </Panel>
                    </div>
                </Panel>
            </TabContent>

            <!-- ── Preview tab ─────────────────────────────────────── -->
            <TabContent value="preview">
                <Panel>
                    <div class="p-6 space-y-6">
                        <div class="bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-gray-700/80 shadow-sm p-6 space-y-6">
                            <Field inline :label="__('Source Type')">
                                <Select v-model="sourceType" :options="sourceTypeOptions" />
                            </Field>
                        </div>

                        <Field inline
                            :label="__('Sample Payload')"
                            :instructions="__('Provide a JSON object that will be passed as data to the template renderer.')"
                        >
                            <CodeEditor
                                v-model="samplePayload"
                                mode="json"
                                class="min-h-48"
                            />
                        </Field>

                        <div>
                            <Button
                                :text="__('Render preview')"
                                variant="primary"
                                :loading="previewing"
                                icon="flash-bolt-lightning"
                                @click="runPreview"
                            />
                        </div>

                        <!-- Result panel -->
                        <template v-if="previewResult !== null">
                            <Panel :heading="__('Rendered output')">
                                <CodeEditor
                                    :model-value="previewResult.rendered || ''"
                                    :mode="editorMode"
                                    read-only
                                    class="min-h-32"
                                />
                            </Panel>

                            <Panel
                                v-if="previewResult.issues?.length"
                                :heading="__('Issues')"
                            >
                                <ul class="p-4 space-y-1">
                                    <li
                                        v-for="issue in previewResult.issues"
                                        :key="issue"
                                        class="text-sm text-red-600 dark:text-red-400"
                                    >{{ issue }}</li>
                                </ul>
                            </Panel>
                        </template>
                    </div>
                </Panel>
            </TabContent>
        </Tabs>

        <!-- ── Footer actions ─────────────────────────────────────── -->
        <div class="mt-6 flex items-center gap-3">
            <Button
                v-if="canDelete && deleteUrl"
                :text="__('Delete')"
                variant="danger"
                @click="showDelete = true"
            />
            <Button
                :text="saveLabel"
                variant="primary"
                :loading="form.processing"
                @click="save"
            />
        </div>

    </div>

    <!-- ── Delete confirmation ────────────────────────────────────── -->
    <ConfirmationModal
        v-if="showDelete"
        :title="__('Delete Template')"
        :body-text="__('Are you sure you want to delete this template? Outbound webhooks using it will have their body source detached.')"
        :confirm-text="__('Delete')"
        danger
        @confirm="destroy"
        @cancel="showDelete = false"
    />
</template>
