<script setup>
/**
 * Integration gallery — pick a destination preset. Uses Statamic's native
 * EmptyStateMenu/EmptyStateItem (the same "choose what to create" pattern the
 * CP uses elsewhere) so it sits natively in the Control Panel.
 */
import { Head } from '@statamic/cms/inertia';
import {
    Header,
    Button,
    EmptyStateMenu,
    EmptyStateItem,
    DocsCallout,
} from '@statamic/cms/ui';

const props = defineProps({
    presets: { type: Array, required: true },
    setupUrlBase: { type: String, required: true },
    outboundUrl: { type: String, required: true },
});

const setupUrl = (handle) => props.setupUrlBase.replace('__PRESET__', handle);
</script>

<template>
    <Head :title="[__('Add integration'), __('Webhook Manager')]" />

    <div class="max-w-page mx-auto">
        <Header :title="__('Add an integration')" icon="arrow-up-right">
            <Button :href="outboundUrl" :text="__('Back to webhooks')" />
        </Header>

        <EmptyStateMenu
            :heading="__('Pick a destination')"
            :subheading="__('Each preset pre-fills the payload, headers and auth — you just provide a URL and choose a trigger.')"
        >
            <EmptyStateItem
                v-for="preset in presets"
                :key="preset.handle"
                :href="setupUrl(preset.handle)"
                :icon="preset.icon"
                :heading="preset.label"
                :description="preset.description"
            />
        </EmptyStateMenu>

        <DocsCallout :topic="__('Integrations')" url="https://statamic.com/addons/goldnead/webhook-manager" />
    </div>
</template>
