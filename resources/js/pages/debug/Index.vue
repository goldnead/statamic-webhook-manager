<script setup>
import { ref } from 'vue';
import { Head } from '@statamic/cms/inertia';
import { Header, Panel, Field, Button, Table, TableRows, TableRow, TableCell, Alert } from '@statamic/cms/ui';

const props = defineProps({
    triggers: { type: Array, default: () => [] },
    resolvers: { type: Array, default: () => [] },
    previewUrl: { type: String, required: true },
    simulateUrl: { type: String, required: true },
});

const template = ref(JSON.stringify({ title: '{{ entry:title }}', site: '{{ site:handle }}' }, null, 2));
const samplePayload = ref(JSON.stringify({ id: '1', title: 'Hello', site: 'default' }, null, 2));
const sourceType = ref('entry');

const previewing = ref(false);
const previewResult = ref(null);

async function preview() {
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
            template: template.value,
            sample_payload: payload,
            source_type: sourceType.value,
        });
        previewResult.value = res.data;
    } catch (e) {
        previewResult.value = { rendered: '', issues: [e?.response?.data?.message ?? e.message] };
    } finally {
        previewing.value = false;
    }
}
</script>

<template>
    <Head :title="__('Webhook Debug')" />

    <Header :title="__('Debug')" icon="instructions" />

    <Panel :heading="__('Registered triggers')" :subheading="__('Internal events that can fire webhooks.')" class="mb-4">
        <Table>
            <TableRows>
                <TableRow header>
                    <TableCell>{{ __('Handle') }}</TableCell>
                    <TableCell>{{ __('Label') }}</TableCell>
                    <TableCell>{{ __('Source') }}</TableCell>
                </TableRow>
                <TableRow v-for="t in triggers" :key="t.handle">
                    <TableCell><code>{{ t.handle }}</code></TableCell>
                    <TableCell>{{ t.label }}</TableCell>
                    <TableCell>{{ t.source_type }}</TableCell>
                </TableRow>
            </TableRows>
        </Table>
    </Panel>

    <Panel :heading="__('Variable resolvers')" :subheading="__('Available namespaces in payload templates.')" class="mb-4">
        <ul class="list-disc list-inside p-4">
            <li v-for="r in resolvers" :key="r.namespace">
                <code>{{ '{{ ' + r.namespace + ':key \x7d\x7d' }}</code>
            </li>
        </ul>
    </Panel>

    <Panel :heading="__('Template preview')" :subheading="__('Render any template against a sample payload.')">
        <div class="p-4 space-y-3">
            <Field :label="__('Source type')" id="source_type">
                <select id="source_type" v-model="sourceType" class="input-text">
                    <option value="entry">entry</option>
                    <option value="form_submission">form_submission</option>
                    <option value="user">user</option>
                    <option value="asset">asset</option>
                </select>
            </Field>
            <Field :label="__('Template')" id="dbg-template">
                <textarea id="dbg-template" v-model="template" rows="6" class="input-text w-full font-mono text-sm"></textarea>
            </Field>
            <Field :label="__('Sample payload (JSON)')" id="dbg-payload">
                <textarea id="dbg-payload" v-model="samplePayload" rows="6" class="input-text w-full font-mono text-sm"></textarea>
            </Field>
            <Button variant="default" :loading="previewing" @click="preview">{{ __('Preview') }}</Button>

            <div v-if="previewResult">
                <Alert v-if="previewResult.issues?.length" variant="warning" :heading="__('Issues')" class="mt-3">
                    <ul class="list-disc list-inside text-sm">
                        <li v-for="(issue, i) in previewResult.issues" :key="i">{{ issue }}</li>
                    </ul>
                </Alert>
                <h4 class="font-semibold text-sm mt-3 mb-1">{{ __('Rendered output') }}</h4>
                <pre class="webhook-manager-snapshot-pre">{{ previewResult.rendered }}</pre>
            </div>
        </div>
    </Panel>
</template>
