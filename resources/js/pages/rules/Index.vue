<script setup>
import { computed } from 'vue';
import { Head, Link } from '@statamic/cms/inertia';
import { router } from '@inertiajs/vue3';
import {
    Header,
    Button,
    Badge,
    DropdownItem,
    EmptyStateMenu,
    EmptyStateItem,
    DocsCallout,
    Listing,
    CommandPaletteItem,
} from '@statamic/cms/ui';

/**
 * Rules listing.
 *
 * Models the Statamic core "Forms Index" pattern: bifurcate between
 * an EmptyStateMenu and a Listing/Header pair so first-time users see
 * a clear call-to-action while experienced users get the full table
 * with search, sort, and bulk actions out of the box.
 */
const props = defineProps({
    rules: { type: Object, required: true },
    initialColumns: { type: Array, required: true },
    actionUrl: { type: String, required: true },
    listingUrl: { type: String, required: true },
    createUrl: { type: String, required: true },
    canCreate: { type: Boolean, default: false },
    triggerOptions: { type: Object, default: () => ({}) },
});

const isEmpty = computed(
    () => !props.rules?.data?.length && !props.rules?.meta?.total,
);

const reloadPage = () => router.reload({ only: ['rules'] });

// Centralised trigger-type colour mapping — consistent with other listing
// pages and dark-mode-safe (Statamic Badge uses semantic colour tokens).
const triggerColor = (triggerType) => {
    switch ((triggerType || '').toLowerCase()) {
        case 'entry.saved':
        case 'entry.created':  return 'green';
        case 'entry.deleted':  return 'red';
        case 'form.submitted': return 'blue';
        case 'user.saved':
        case 'user.created':   return 'purple';
        case 'user.deleted':   return 'red';
        default:               return 'default';
    }
};

function toggle(rule) {
    if (!rule.toggle_url) return;
    router.patch(rule.toggle_url, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="[__('Rules'), __('Webhook Manager')]" />

    <div v-if="isEmpty" class="max-w-page mx-auto">
        <header class="py-8 pt-16 text-center">
            <h1 class="text-[25px] font-medium antialiased flex justify-center items-center gap-2 sm:gap-3">
                {{ __('Rules') }}
            </h1>
        </header>

        <EmptyStateMenu :heading="__('webhook-manager::messages.rules_empty_intro')">
            <EmptyStateItem
                v-if="canCreate"
                :href="createUrl"
                icon="filter"
                :heading="__('Create Rule')"
                :description="__('webhook-manager::messages.rules_create_description')"
            />
        </EmptyStateMenu>

        <DocsCallout :topic="__('Rules')" url="https://statamic.dev/" />
    </div>

    <div v-else class="max-w-page mx-auto">
        <Header :title="__('Rules')" icon="filter">
            <CommandPaletteItem
                v-if="canCreate"
                category="Actions"
                :text="__('Create Rule')"
                icon="filter"
                :url="createUrl"
                v-slot="{ text, url }"
            >
                <Button :href="url" :text="text" variant="primary" />
            </CommandPaletteItem>
        </Header>

        <Listing
            :url="listingUrl"
            :columns="initialColumns"
            :action-url="actionUrl"
            preferences-prefix="webhook-manager.rules"
            push-query
            @refreshing="reloadPage"
        >
            <template #cell-name="{ row: rule }">
                <Link :href="rule.edit_url" class="font-semibold">{{ rule.name }}</Link>
                <span class="block text-2xs text-gray-600 dark:text-gray-400">{{ rule.handle }}</span>
            </template>

            <template #cell-trigger_type="{ row: rule }">
                <Badge
                    :color="triggerColor(rule.trigger_type)"
                    :text="rule.trigger_label || rule.trigger_type"
                />
            </template>

            <template #cell-action_count="{ row: rule }">
                <Badge
                    :color="rule.action_count === 0 ? 'red' : 'default'"
                    :text="String(rule.action_count)"
                />
            </template>

            <template #cell-order_index="{ row: rule }">
                <span class="text-sm tabular-nums text-gray-600 dark:text-gray-400">
                    {{ rule.order_index }}
                </span>
            </template>

            <template #cell-enabled="{ row: rule }">
                <Badge
                    :color="rule.enabled ? 'green' : 'default'"
                    :text="rule.enabled ? __('Active') : __('Disabled')"
                />
            </template>

            <template #prepended-row-actions="{ row: rule }">
                <DropdownItem
                    v-if="rule.can_edit"
                    icon="cog"
                    :text="__('Edit')"
                    :href="rule.edit_url"
                />
                <DropdownItem
                    v-if="rule.can_toggle"
                    icon="fieldtype-toggle"
                    :text="rule.enabled ? __('Disable') : __('Enable')"
                    @click="toggle(rule)"
                />
            </template>
        </Listing>

        <DocsCallout :topic="__('Rules')" url="https://statamic.dev/" />
    </div>
</template>
