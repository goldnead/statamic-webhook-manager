<script setup>
import { ref, computed } from 'vue';
import { Head } from '@statamic/cms/inertia';
import { useForm, router } from '@inertiajs/vue3';
import { Header, Button, Panel, Field, Input, Alert, ConfirmationModal } from '@statamic/cms/ui';

const props = defineProps({
    template: { type: Object, required: true },
    typeOptions: { type: Object, required: true },
    namespaces: { type: Array, default: () => [] },
    isNew: { type: Boolean, default: false },
    saveUrl: { type: String, required: true },
    deleteUrl: { type: String, default: null },
    previewUrl: { type: String, required: true },
    indexUrl: { type: String, required: true },
});

const form = useForm({
    name: props.template.name ?? '',
    handle: props.template.handle ?? '',
    type: props.template.type ?? 'outbound_body',
    body: props.template.body ?? '',
    meta: props.template.meta ?? null,
});

const showDelete = ref(false);
const previewing = ref(false);
const previewResult = ref(null);
const samplePayload = ref('{\n  "id": 1,\n  "title": "Sample entry",\n  "site": "default"\n}');

const pageTitle = computed(() => props.isNew
    ? __('Create template')
    : props.template.name);

function save() {
    const verb = props.isNew ? 'post' : 'patch';
    form[verb](props.saveUrl, { preserveScroll: true });
}

async function runPreview() {
    previewing.value = true;
    previewResult.value = null;
    try {
        const payload = samplePayload.value.trim() ? JSON.parse(samplePayload.value) : {};
        const res = await window.axios.post(props.previewUrl, {
            template: form.body,
            sample_payload: payload,
            source_type: 'entry',
        });
        previewResult.value = res.data;
    } catch (e) {
        previewResult.value = {
            rendered: '',
            issues: [e?.response?.data?.message ?? e?.message ?? __('Preview failed.')],
        };
    } finally {
        previewing.value = false;
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

    <Header :title="pageTitle" icon="content-writing">
        <Button variant="primary" :loading="form.processing" @click="save">
            {{ __('Save') }}
        </Button>
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
                   :instructions="__('Lowercase. Outbound webhooks reference templates by handle.')">
                <Input id="handle" v-model="form.handle" pattern="[a-z0-9_-]+" />
            </Field>
            <Field :label="__('Type')" id="type" :required="true" :error="form.errors.type" class="md:col-span-2">
                <select id="type" v-model="form.type" class="input-text">
                    <option v-for="(label, value) in typeOptions" :key="value" :value="value">{{ label }}</option>
                </select>
            </Field>
        </div>
    </Panel>

    <Panel :heading="__('Body')" class="mb-4">
        <div class="p-4">
            <Field :label="__('Template body')" id="body" :required="true" :error="form.errors.body"
                   :instructions="__('Use tokens like {{ entry:title }} or {{ system:timestamp_iso|default(\'-\') }}.')">
                <textarea id="body" v-model="form.body" rows="14"
                          class="input-text w-full font-mono text-sm"
                          placeholder='{ "id": "{{ entry:id }}", "title": "{{ entry:title }}" }'></textarea>
            </Field>
            <div v-if="namespaces.length" class="mt-3 text-xs text-gray-600">
                <strong>{{ __('Available namespaces:') }}</strong>
                <code v-for="ns in namespaces" :key="ns" class="ml-2">{{ ns }}</code>
            </div>
        </div>
    </Panel>

    <Panel :heading="__('Preview')" class="mb-4">
        <div class="p-4">
            <Field :label="__('Sample payload (JSON)')" id="sample_payload"
                   :instructions="__('Tokens are resolved against this payload via the registered variable resolvers.')">
                <textarea id="sample_payload" v-model="samplePayload" rows="6"
                          class="input-text w-full font-mono text-sm"></textarea>
            </Field>
            <div class="mt-3">
                <Button variant="default" :loading="previewing" @click="runPreview">
                    {{ __('Render preview') }}
                </Button>
            </div>
            <div v-if="previewResult" class="mt-4 grid grid-cols-1 gap-3">
                <div>
                    <h4 class="text-xs uppercase font-semibold text-gray-600 mb-1">{{ __('Rendered output') }}</h4>
                    <pre class="text-xs bg-gray-100 dark:bg-gray-800 p-3 rounded overflow-auto whitespace-pre-wrap">{{ previewResult.rendered || '(empty)' }}</pre>
                </div>
                <div v-if="previewResult.issues && previewResult.issues.length">
                    <h4 class="text-xs uppercase font-semibold text-red-600 mb-1">{{ __('Issues') }}</h4>
                    <ul class="text-xs text-red-600 list-disc list-inside">
                        <li v-for="(issue, idx) in previewResult.issues" :key="idx">{{ issue }}</li>
                    </ul>
                </div>
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
        :title="__('Delete template')"
        :body-text="__('This permanently removes the template. Any outbound webhook that references it by handle will be detached and use its inline payload instead.')"
        :button-text="__('Delete')"
        :danger="true"
        @confirm="destroy"
        @update:open="showDelete = $event"
    />
</template>
