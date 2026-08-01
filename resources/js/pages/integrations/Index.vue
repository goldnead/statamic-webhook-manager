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

            <!-- Every other index screen in this addon has an empty state.
                 This one rendered a heading and a subheading over nothing when
                 the registry came back empty, which is what happens the moment
                 a site removes the built-in presets. -->
            <EmptyStateItem
                v-if="!presets.length"
                :href="outboundUrl"
                icon="arrow-up-right"
                :heading="__('No integrations available')"
                :description="__('No presets are registered. Create an outbound webhook by hand, or register a preset from a service provider.')"
            />
        </EmptyStateMenu>

        <DocsCallout :topic="__('Integrations')" url="https://github.com/goldnead/statamic-webhook-manager#integration-presets" />
    </div>
</template>
