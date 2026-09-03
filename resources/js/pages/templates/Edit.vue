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
    CodeEditor,
    Tabs,
    TabList,
    TabTrigger,
    TabContent,
    Panel,
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

// Rejections that reach this page outside `form.submit()` — a refused delete.
// useForm's bag only carries what the form itself sent.
const actionErrors = ref({});

const allErrors = computed(() => ({ ...form.errors, ...actionErrors.value }));

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
        onError: (errors) => { actionErrors.value = errors || {}; showDelete.value = false; },
        onSuccess: () => { actionErrors.value = {}; showDelete.value = false; },
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
        <!--
            Header order is core's: the `…` dropdown first, the primary action
            last (ui-vocabulary §24). `Dropdown` renders its own dots trigger,
            so there is no `#trigger` here.

            Delete used to be a `Button variant="danger"` in a footer bar below
            the tabs. Core uses `danger` in exactly one place — the confirm
            button inside a modal — and a destructive page action is a
            `DropdownItem variant="destructive"`. The footer also carried a
            second Save, duplicating the one in the header; it is gone with it.
        -->
        <Header :title="pageTitle" icon="layout-grid">
            <Dropdown v-if="canDelete && deleteUrl">
                <DropdownMenu>
                    <DropdownItem
                        variant="destructive"
                        icon="trash"
                        :text="__('Delete Template')"
                        @click="showDelete = true"
                    />
                </DropdownMenu>
            </Dropdown>
            <Button
                :text="saveLabel"
                variant="primary"
                :loading="form.processing"
                @click="save"
            />
            <!-- `action`, not `@click`: CommandPaletteItem takes a function
                 prop and logs "You must provide a `url` string or `action`
                 function" for anything else, so this entry did nothing when
                 picked from the palette. `danger` is not one of its props. -->
            <CommandPaletteItem
                v-if="canDelete && deleteUrl"
                category="Actions"
                :text="__('Delete Template')"
                icon="trash"
                :action="() => (showDelete = true)"
            />
        </Header>

        <!-- ── Contextual hint ──────────────────────────────────────
             No `variant`: `info` is not one of Alert's four
             (default/warning/error/success) and fell through to `default`
             anyway. What this says — here is what a template is for — is
             exactly `default`. -->
        <Alert class="mb-6">
            {{ __('webhook-manager::messages.templates_edit_hint') }}
        </Alert>

        <!-- ── Global error banner ─────────────────────────────────
             `type="error"` is not a prop of Alert; it landed in $attrs and the
             banner rendered in the neutral style. The prop is `variant`. -->
        <Alert
            v-if="Object.keys(allErrors).length"
            variant="error"
            class="mb-6"
            data-webhook-form-errors
        >
            <ul class="list-disc list-inside">
                <li v-for="(err, key) in allErrors" :key="key">{{ err }}</li>
            </ul>
        </Alert>

        <!-- ── Tabs ───────────────────────────────────────────────── -->
        <Tabs v-model="activeTab">
            <TabList>
                <!-- `label` and `has-error` are not TabTrigger props (it reads
                     `text`/`name` and its default slot), so both landed in
                     $attrs and every trigger on this page rendered with no
                     text at all — a blank strip where the tab bar belongs, and
                     no way to reach Body or Preview. The other three edit
                     pages use the default slot; this one now matches them. -->
                <TabTrigger value="general" :class="{ 'text-red-500': tabsWithErrors.has('general') }">
                    {{ __('General') }}
                </TabTrigger>
                <TabTrigger value="body" :class="{ 'text-red-500': tabsWithErrors.has('body') }">
                    {{ __('Body') }}
                </TabTrigger>
                <TabTrigger value="preview">
                    {{ __('Preview') }}
                </TabTrigger>
            </TabList>

            <!-- ── General tab ─────────────────────────────────────── -->
            <TabContent value="general">
                <Panel>
                    <Card inset class="p-6 space-y-6">
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
                    </Card>
                </Panel>
            </TabContent>

            <!-- ── Body tab ────────────────────────────────────────── -->
            <TabContent value="body">
                <Panel>
                    <Card inset class="p-6 space-y-4">
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
                    </Card>
                </Panel>

                <!--
                    The variable list is its own Panel, a sibling rather than a
                    Panel nested inside the body Panel's padding — grey inside
                    grey is not a shape core uses. `collapsible`/`collapsed`
                    are gone with it: Panel has no such props (heading,
                    subheading, icon), so they landed in $attrs and the panel
                    was never collapsed in the first place.
                -->
                <Panel
                    v-if="namespaces.length"
                    :heading="__('Available variables')"
                    class="mt-4"
                >
                    <Card>
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            <li
                                v-for="ns in namespaces"
                                :key="ns"
                                class="flex items-center justify-between py-2 text-sm font-mono first:pt-0 last:pb-0"
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
                    </Card>
                </Panel>
            </TabContent>

            <!-- ── Preview tab ─────────────────────────────────────── -->
            <TabContent value="preview">
                <Panel>
                    <Card inset class="p-6 space-y-6">
                        <Field inline :label="__('Source Type')">
                            <Select v-model="sourceType" :options="sourceTypeOptions" />
                        </Field>

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
                    </Card>
                </Panel>

                <!-- Result panels, siblings of the form panel rather than
                     Panels nested inside its padding. -->
                <template v-if="previewResult !== null">
                    <Panel :heading="__('Rendered output')" class="mt-4">
                        <Card>
                            <CodeEditor
                                :model-value="previewResult.rendered || ''"
                                :mode="editorMode"
                                read-only
                                class="min-h-32"
                            />
                        </Card>
                    </Panel>

                    <Panel
                        v-if="previewResult.issues?.length"
                        :heading="__('Issues')"
                        class="mt-4"
                    >
                        <Card>
                            <ul class="space-y-1">
                                <li
                                    v-for="issue in previewResult.issues"
                                    :key="issue"
                                    class="text-sm text-red-600 dark:text-red-400"
                                >{{ issue }}</li>
                            </ul>
                        </Card>
                    </Panel>
                </template>
            </TabContent>
        </Tabs>

    </div>

    <!-- ── Delete confirmation ────────────────────────────────────── -->
    <ConfirmationModal
        v-if="canDelete && deleteUrl"
        :open="showDelete"
        :title="__('Delete Template')"
        :body-text="__('Are you sure you want to delete this template? Outbound webhooks using it will have their body source detached.')"
        :button-text="__('Delete')"
        :danger="true"
        @confirm="destroy"
        @update:open="showDelete = $event"
    />
</template>
