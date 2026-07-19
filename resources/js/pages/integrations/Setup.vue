<script setup>
/**
 * Slim setup form for a chosen integration preset. Mirrors Statamic's native
 * config-form layout (gray section panel → white card → inline fields).
 */
import { computed } from 'vue';
import { Head } from '@statamic/cms/inertia';
import { useForm } from '@statamic/cms/inertia';
import {
    Header,
    Button,
    Panel,
    Field,
    Input,
    Textarea,
    Select,
    CodeEditor,
} from '@statamic/cms/ui';

const props = defineProps({
    preset: { type: Object, required: true },
    triggerOptions: { type: Object, required: true },
    galleryUrl: { type: String, required: true },
    saveUrl: { type: String, required: true },
});

const triggerOptionsArray = computed(() =>
    Object.entries(props.triggerOptions).map(([value, label]) => ({ value, label })),
);

const triggerKeys = Object.keys(props.triggerOptions);
const initial = {
    name: props.preset.label,
    trigger_type: triggerKeys.includes('entry.published') ? 'entry.published' : (triggerKeys[0] ?? ''),
};
props.preset.fields.forEach((f) => {
    initial[f.handle] = f.default ?? '';
});

const form = useForm(initial);

function submit() {
    if (!props.saveUrl) return;
    // form.submit('post', url) — not form.post(url): the dynamic-verb helper
    // crashes ("reading 'url'") in this Inertia v2 build, same as the edit forms.
    form.submit('post', props.saveUrl, { preserveScroll: true });
}
</script>

<template>
    <Head :title="[preset.label, __('Webhook Manager')]" />

    <div class="max-w-page mx-auto">
        <Header :title="`${__('Set up')} ${preset.label}`" :icon="preset.icon">
            <Button :href="galleryUrl" :text="__('Back')" />
            <Button
                variant="primary"
                :text="__('Create')"
                :disabled="form.processing"
                @click="submit"
            />
        </Header>

        <Panel class="mt-4">
            <div class="bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-gray-700/80 shadow-sm p-6 space-y-6">
                <Field inline :label="__('Name')" :error="form.errors.name" required>
                    <Input v-model="form.name" autofocus />
                </Field>

                <Field inline :label="__('Trigger')" :error="form.errors.trigger_type" required
                    :instructions="__('The Statamic event that fires this integration.')">
                    <Select v-model="form.trigger_type" :options="triggerOptionsArray" />
                </Field>

                <Field
                    v-for="f in preset.fields"
                    :key="f.handle"
                    inline
                    :label="f.label"
                    :instructions="f.instructions"
                    :error="form.errors[f.handle]"
                    :required="f.required"
                >
                    <Textarea v-if="f.type === 'textarea'" v-model="form[f.handle]" :rows="3" />
                    <CodeEditor
                        v-else-if="f.type === 'code'"
                        v-model="form[f.handle]"
                        mode="json"
                        :line-numbers="true"
                        class="font-mono text-sm"
                    />
                    <Input v-else v-model="form[f.handle]" :class="{ 'font-mono': String(f.handle).includes('url') }" />
                </Field>
            </div>
        </Panel>
    </div>
</template>
