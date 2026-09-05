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
    <Head :title="[__('webhook-manager::messages.cp.page_add_integration'), __('webhook-manager::messages.cp.app_name')]" />

    <div class="max-w-page mx-auto">
        <Header :title="__('webhook-manager::messages.cp.integrations_add_heading')" icon="arrow-up-right">
            <Button :href="outboundUrl" :text="__('webhook-manager::messages.cp.back_to_webhooks')" />
        </Header>

        <EmptyStateMenu
            :heading="__('webhook-manager::messages.cp.integrations_pick_destination')"
            :subheading="__('webhook-manager::messages.cp.integrations_intro')"
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
                :heading="__('webhook-manager::messages.cp.integrations_empty_heading')"
                :description="__('webhook-manager::messages.cp.integrations_empty_sub')"
            />
        </EmptyStateMenu>

        <DocsCallout :topic="__('webhook-manager::messages.cp.page_integrations')" url="https://github.com/goldnead/statamic-webhook-manager#integration-presets" />
    </div>
</template>
