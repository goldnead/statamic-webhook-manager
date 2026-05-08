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
 * Templates listing.
 *
 * Models the Statamic core "Forms Index" pattern: bifurcate between
 * an EmptyStateMenu and a Listing/Header pair so first-time users see
 * a clear call-to-action while experienced users get the full table
 * with search, sort, filters and bulk actions out of the box.
 */
const props = defineProps({
    templates: { type: Object, required: true },
    initialColumns: { type: Array, required: true },
    actionUrl: { type: String, required: true },
    listingUrl: { type: String, required: true },
    createUrl: { type: String, required: true },
    canCreate: { type: Boolean, default: false },
    typeOptions: { type: Object, default: () => ({}) },
});

const isEmpty = computed(
    () => !props.templates?.data?.length && !props.templates?.meta?.total,
);

const reloadPage = () => router.reload({ only: ['templates'] });

// Badge colour per template type — centralised here so PHP doesn't need
// to know about Statamic's semantic colour tokens.
const typeColor = (type) => {
    switch (type) {
        case 'outbound_body':   return 'blue';
        case 'notification':    return 'amber';
        default:                return 'gray';
    }
};
</script>

<template>
    <Head :title="[__('Templates'), __('Webhook Manager')]" />

    <!-- ── Empty state ─────────────────────────────────────────────── -->
    <div v-if="isEmpty" class="max-w-page mx-auto">
        <header class="py-8 pt-16 text-center">
            <h1 class="mb-3 font-bold text-3xl">{{ __('Templates') }}</h1>
        </header>

        <EmptyStateMenu :heading="__('webhook-manager::messages.templates_empty_intro')">
            <EmptyStateItem
                v-if="canCreate"
                :href="createUrl"
                icon="layouts"
                :heading="__('Create Template')"
                :description="__('webhook-manager::messages.templates_create_description')"
            />
        </EmptyStateMenu>

        <DocsCallout topic="Templates" url="https://statamic.com/addons/goldnead/webhook-manager/docs/templates" />
    </div>

    <!-- ── Listing ─────────────────────────────────────────────────── -->
    <div v-else class="max-w-page mx-auto">
        <Header :title="__('Templates')" icon="layouts">
            <Button
                v-if="canCreate"
                :href="createUrl"
                :text="__('Create Template')"
                variant="primary"
            />
            <CommandPaletteItem
                category="Actions"
                :text="__('Create Template')"
                :url="createUrl"
            />
        </Header>

        <Listing
            :items="templates"
            :columns="initialColumns"
            :url="listingUrl"
            :action-url="actionUrl"
            preferences-prefix="webhook-manager.templates"
            @refreshing="reloadPage"
        >
            <!-- name cell — links to edit page -->
            <template #cell-name="{ row }">
                <Link :href="row.edit_url">{{ row.name }}</Link>
            </template>

            <!-- type cell — colour-coded badge -->
            <template #cell-type="{ row }">
                <Badge
                    :color="typeColor(row.type)"
                    :text="row.type_label ?? row.type"
                />
            </template>

            <!-- row actions -->
            <template #prepended-row-actions="{ row }">
                <DropdownItem
                    v-if="row.can_edit"
                    :text="__('Edit')"
                    :href="row.edit_url"
                    icon="cog"
                />
                <DropdownItem
                    v-if="row.duplicate_url"
                    :text="__('Duplicate')"
                    :href="row.duplicate_url"
                    icon="copy"
                />
                <DropdownItem
                    v-if="row.can_delete"
                    :text="__('Delete')"
                    icon="trash"
                    danger
                    @click="router.delete(row.delete_url, { preserveScroll: true, onSuccess: reloadPage })"
                />
            </template>
        </Listing>
    </div>
</template>
