<script setup>
import { ref, computed } from 'vue';
import { Head } from '@statamic/cms/inertia';
import {
    Header,
    Panel,
    Listing,
    Field,
    Select,
    CodeEditor,
    Button,
    Alert,
    Badge,
} from '@statamic/cms/ui';

const props = defineProps({
    triggers:    { type: Array,  default: () => [] },
    resolvers:   { type: Array,  default: () => [] },
    previewUrl:  { type: String, required: true },
    simulateUrl: { type: String, default: null },
});

// ── Trigger Inspector columns ──────────────────────────────────────────────
const inspectorColumns = [
    { handle: 'handle',      label: __('Handle'),      sortable: false },
    { handle: 'label',       label: __('Label'),        sortable: false },
    { handle: 'source_type', label: __('Source Type'),  sortable: false },
];

// ── Template Preview state ─────────────────────────────────────────────────
const template = ref(JSON.stringify({ title: '{{ entry:title }}', site: '{{ site:handle }}' }, null, 2));
const samplePayload = ref(JSON.stringify({ id: '1', title: 'Hello', site: 'default' }, null, 2));
const sourceType = ref('entry');

const sourceTypeOptions = [
    { value: 'entry',           label: __('Entry') },
    { value: 'form_submission', label: __('Form Submission') },
    { value: 'user',            label: __('User') },
    { value: 'asset',           label: __('Asset') },
];

const previewing = ref(false);
const previewResult = ref(null);

async function runPreview() {
    let payload;
    try {
        payload = JSON.parse(samplePayload.value);
    } catch (e) {
        previewResult.value = { rendered: '', issues: [`Invalid sample JSON: ${e.message}`] };
        return;
    }
    previewing.value = true;
    previewResult.value = null;
    try {
        const res = await window.axios.post(props.previewUrl, {
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
        : __('Rendered successfully.')
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
        simulateResult.value = { success: false, message: `Invalid JSON: ${e.message}`, response: null };
        return;
    }
    simulating.value = true;
    simulateResult.value = null;
    try {
        const res = await window.axios.post(props.simulateUrl, {
            trigger:        selectedTrigger.value,
            sample_payload: payload,
        });
        simulateResult.value = { success: true, message: __('Trigger simulated.'), response: res.data };
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
    { handle: 'namespace', label: __('Namespace'), sortable: false },
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
</script>

<template>
    <Head :title="[__('Debug'), __('Webhook Manager')]" />

    <div class="max-w-page mx-auto">

        <Header :title="__('Debug')" icon="code-block" />

        <!-- ── Trigger Inspector ───────────────────────────────────────── -->
        <Panel
            :heading="__('Trigger Inspector')"
            :subheading="__('All triggers registered with the Webhook Manager.')"
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
                    <Badge :text="value" color="gray" />
                </template>
            </Listing>
        </Panel>

        <!-- ── Resolver Inspector ─────────────────────────────────────── -->
        <Panel
            v-if="resolvers.length > 0"
            :heading="__('Resolver Namespaces')"
            :subheading="__('Registered payload resolvers and their template namespaces.')"
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

        <!-- ── Template Preview ───────────────────────────────────────── -->
        <Panel
            :heading="__('Template Preview')"
            :subheading="__('Render a Webhook Manager template against a sample payload to verify output.')"
        >
            <div class="space-y-4 p-4">

                <Field :handle="'template'" :display="__('Template (JSON)')" :instructions="__('Enter a JSON template using Antlers or plain values.')">
                    <CodeEditor v-model="template" mode="json" />
                </Field>

                <Field :handle="'source_type'" :display="__('Source Type')">
                    <Select v-model="sourceType" :options="sourceTypeOptions" />
                </Field>

                <Field :handle="'sample_payload'" :display="__('Sample Payload')" :instructions="__('JSON object that will be passed to the template renderer.')">
                    <CodeEditor v-model="samplePayload" mode="json" />
                </Field>

                <div>
                    <Button
                        :text="previewing ? __('Rendering…') : __('Preview')"
                        variant="primary"
                        :disabled="previewing"
                        @click="runPreview"
                    />
                </div>

                <template v-if="previewResult">
                    <Alert :variant="previewVariant" :message="previewMessage" />
                    <Field
                        v-if="previewResult.rendered"
                        :handle="'preview_output'"
                        :display="__('Rendered Output')"
                    >
                        <CodeEditor :model-value="previewResult.rendered" read-only />
                    </Field>
                </template>

            </div>
        </Panel>

        <!-- ── Simulate Trigger ───────────────────────────────────────── -->
        <Panel
            v-if="simulateUrl && triggers.length > 0"
            :heading="__('Simulate Trigger')"
            :subheading="__('Fire a registered trigger with a sample payload to test outbound webhooks end-to-end.')"
        >
            <div class="space-y-4 p-4">

                <Field :handle="'trigger'" :display="__('Trigger')">
                    <Select v-model="selectedTrigger" :options="triggerOptions" />
                </Field>

                <Field :handle="'trigger_payload'" :display="__('Sample Payload')" :instructions="__('JSON object that will be dispatched as the trigger payload.')">
                    <CodeEditor v-model="triggerPayload" mode="json" />
                </Field>

                <div>
                    <Button
                        :text="simulating ? __('Running…') : __('Run')"
                        variant="primary"
                        :disabled="simulating"
                        @click="runSimulate"
                    />
                </div>

                <template v-if="simulateResult">
                    <Alert
                        :variant="simulateResult.success ? 'success' : 'error'"
                        :message="simulateResult.message"
                    />
                    <Field
                        v-if="simulateResponseJson"
                        :handle="'simulate_response'"
                        :display="__('Response')"
                    >
                        <CodeEditor :model-value="simulateResponseJson" mode="json" read-only />
                    </Field>
                </template>

            </div>
        </Panel>

    </div>
</template>
