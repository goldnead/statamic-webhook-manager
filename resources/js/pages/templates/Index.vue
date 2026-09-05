<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@statamic/cms/inertia';
import { router } from '@statamic/cms/inertia';
import {
    Header,
    Button,
    Badge,
    DropdownItem,
    EmptyStateMenu,
    EmptyStateItem,
    DocsCallout,
    Alert,
    Icon,
    Listing,
    CommandPaletteItem,
    ConfirmationModal,
} from '@statamic/cms/ui';

// Persistent-but-dismissible intro shown above the populated listing.
// The dismissed flag is remembered per browser so power users only see it once.
const INTRO_KEY = 'webhook-manager.templates.intro-dismissed';
const introDismissed = ref(
    typeof localStorage !== 'undefined' && localStorage.getItem(INTRO_KEY) === '1',
);
function dismissIntro() {
    introDismissed.value = true;
    try { localStorage.setItem(INTRO_KEY, '1'); } catch (e) { /* private mode */ }
}

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
    // Null on purpose — see the controller. There is no bulk-action endpoint
    // for this listing, and a non-null value here is what makes core draw a
    // checkbox column whose menu can never fill.
    actionUrl: { type: String, default: null },
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
        default:                return 'default';
    }
};

// Rejections from the actions on this listing. Nothing here is a field, so
// whatever comes back is shown in the banner above the rows.
const actionErrors = ref({});

// Deleting a template detaches it from every outbound webhook that used it as
// a body source, so it gets the same confirmation core puts in front of every
// destructive action — and the same one templates/Edit.vue already had. This
// listing was the one place a single click deleted without asking.
const pendingDelete = ref(null);
const confirmingDelete = computed({
    get: () => pendingDelete.value !== null,
    set: (open) => { if (!open) pendingDelete.value = null; },
});

function confirmDestroy(row) {
    if (!row.delete_url) return;
    pendingDelete.value = row;
}

// Was an inline `router.delete(..., { onSuccess: reloadPage })` in the
// template with no error branch at all: a refused delete left the row in
// place and said nothing.
function destroy() {
    const row = pendingDelete.value;
    pendingDelete.value = null;

    if (!row?.delete_url) return;

    router.delete(row.delete_url, {
        preserveScroll: true,
        onError: (errors) => { actionErrors.value = errors || {}; },
        onSuccess: () => { actionErrors.value = {}; reloadPage(); },
    });
}
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
                icon="layout-grid"
                :heading="__('Create Template')"
                :description="__('webhook-manager::messages.templates_create_description')"
            />
        </EmptyStateMenu>

        <DocsCallout :topic="__('Templates')" url="https://github.com/goldnead/statamic-webhook-manager#templates" />
    </div>

    <!-- ── Listing ─────────────────────────────────────────────────── -->
    <div v-else class="max-w-page mx-auto">
        <Header :title="__('Templates')" icon="layout-grid">
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

        <!-- No `variant`: `info` is not one of Alert's four
             (default/warning/error/success). A dismissible intro that explains
             what templates are is `default`. -->
        <Alert v-if="!introDismissed" class="mb-6">
            <div class="flex items-start justify-between gap-3">
                <span>{{ __('webhook-manager::messages.templates_help') }}</span>
                <button
                    type="button"
                    class="shrink-0 text-gray-500 hover:text-gray-800 dark:hover:text-gray-200"
                    :aria-label="__('Dismiss')"
                    @click="dismissIntro"
                >
                    <Icon name="x" class="h-4 w-4" />
                </button>
            </div>
        </Alert>

        <!-- What the server said when an action from this listing was refused.
             There is no field here to hang a message on, so everything that comes
             back is shown above the listing. Structural today: the endpoints these
             buttons reach can only refuse with a 403, which Inertia does not route
             through `onError`. It is the net for the day one of them refuses with a
             reason, the way LeadHub 1.7.0 refuses a delete that still has children. -->
        <Alert
            v-if="Object.keys(actionErrors).length"
            variant="error"
            class="mb-4"
            data-webhook-form-errors
        >
            <ul class="list-disc list-inside space-y-0.5">
                <li v-for="(err, key) in actionErrors" :key="key">{{ err }}</li>
            </ul>
        </Alert>

        <Listing
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
                    icon="duplicate"
                />
                <!-- `danger` is not a DropdownItem prop — the only two values
                     of `variant` are `default` and `destructive`, so the item
                     rendered in the ordinary style with a stray attribute. -->
                <DropdownItem
                    v-if="row.can_delete"
                    :text="__('Delete')"
                    icon="trash"
                    variant="destructive"
                    @click="confirmDestroy(row)"
                />
            </template>
        </Listing>
    </div>

    <!-- ── Delete confirmation ────────────────────────────────────── -->
    <ConfirmationModal
        :open="confirmingDelete"
        :title="__('Delete Template')"
        :body-text="__('Are you sure you want to delete this template? Outbound webhooks using it will have their body source detached.')"
        :button-text="__('Delete')"
        :danger="true"
        @confirm="destroy"
        @update:open="confirmingDelete = $event"
    />
</template>
