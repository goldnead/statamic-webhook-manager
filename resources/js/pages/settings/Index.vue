<script setup>
import { Head } from '@statamic/cms/inertia';
import { Header, Panel, Alert } from '@statamic/cms/ui';

const props = defineProps({
    config: { type: Object, required: true },
    configPath: { type: String, required: true },
    publishCommand: { type: String, required: true },
});
</script>

<template>
    <Head :title="__('Webhook Settings')" />

    <Header :title="__('Settings')" icon="settings-gear" />

    <Alert
        variant="default"
        :heading="__('Settings live in the config file')"
        class="mb-6"
    >
        <p class="text-sm">
            {{ __('Edit') }} <code>{{ configPath }}</code>
            {{ __('to change retry policy, logging mode, masking rules, route prefixes, etc.') }}
        </p>
        <p class="text-sm mt-2">
            {{ __('If the config file is not yet published, run:') }}
            <code class="block mt-1 p-2 bg-gray-100 rounded">{{ publishCommand }}</code>
        </p>
    </Alert>

    <Panel :heading="__('Effective configuration')" :subheading="__('Read-only snapshot of the merged config.')">
        <pre class="webhook-manager-snapshot-pre">{{ JSON.stringify(config, null, 2) }}</pre>
    </Panel>
</template>
