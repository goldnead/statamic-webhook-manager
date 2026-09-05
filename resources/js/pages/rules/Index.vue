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
} from '@statamic/cms/ui';

// Persistent-but-dismissible intro shown above the populated listing.
// The dismissed flag is remembered per browser so power users only see it once.
const INTRO_KEY = 'webhook-manager.rules.intro-dismissed';
const introDismissed = ref(
    typeof localStorage !== 'undefined' && localStorage.getItem(INTRO_KEY) === '1',
);
function dismissIntro() {
    introDismissed.value = true;
    try { localStorage.setItem(INTRO_KEY, '1'); } catch (e) { /* private mode */ }
}

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
    // Null on purpose — see the controller. There is no bulk-action endpoint
    // for this listing, and a non-null value here is what makes core draw a
    // checkbox column whose menu can never fill.
    actionUrl: { type: String, default: null },
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
    router.patch(rule.toggle_url, {}, {
        preserveScroll: true,
        onError: (errors) => { actionErrors.value = errors || {}; },
        onSuccess: () => { actionErrors.value = {}; },
    });
}

// Rejections from the actions on this listing. Nothing here is a field, so
// whatever comes back is shown in the banner above the rows.
const actionErrors = ref({});
</script>

<template>
    <Head :title="[__('webhook-manager::messages.cp.page_rules'), __('webhook-manager::messages.cp.app_name')]" />

    <div v-if="isEmpty" class="max-w-page mx-auto">
        <header class="py-8 pt-16 text-center">
            <h1 class="text-[25px] font-medium antialiased flex justify-center items-center gap-2 sm:gap-3">
                {{ __('webhook-manager::messages.cp.page_rules') }}
            </h1>
        </header>

        <EmptyStateMenu :heading="__('webhook-manager::messages.rules_empty_intro')">
            <EmptyStateItem
                v-if="canCreate"
                :href="createUrl"
                icon="filter"
                :heading="__('webhook-manager::messages.cp.rules_create_heading')"
                :description="__('webhook-manager::messages.rules_create_description')"
            />
        </EmptyStateMenu>

        <DocsCallout :topic="__('webhook-manager::messages.cp.page_rules')" url="https://github.com/goldnead/statamic-webhook-manager#rules" />
    </div>

    <div v-else class="max-w-page mx-auto">
        <Header :title="__('webhook-manager::messages.cp.page_rules')" icon="filter">
            <CommandPaletteItem
                v-if="canCreate"
                category="Actions"
                :text="__('webhook-manager::messages.cp.rules_create_heading')"
                icon="filter"
                :url="createUrl"
                v-slot="{ text, url }"
            >
                <Button :href="url" :text="text" variant="primary" />
            </CommandPaletteItem>
        </Header>

        <!-- No `variant`: `info` is not one of Alert's four
             (default/warning/error/success). A dismissible intro that explains
             what rules are is `default`. -->
        <Alert v-if="!introDismissed" class="mb-6">
            <div class="flex items-start justify-between gap-3">
                <span>{{ __('webhook-manager::messages.rules_help') }}</span>
                <button
                    type="button"
                    class="shrink-0 text-gray-500 hover:text-gray-800 dark:hover:text-gray-200"
                    :aria-label="__('webhook-manager::messages.cp.btn_dismiss')"
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
                    :text="rule.enabled ? __('webhook-manager::messages.cp.status_active') : __('webhook-manager::messages.cp.status_disabled')"
                />
            </template>

            <template #prepended-row-actions="{ row: rule }">
                <DropdownItem
                    v-if="rule.can_edit"
                    icon="cog"
                    :text="__('webhook-manager::messages.cp.action_edit')"
                    :href="rule.edit_url"
                />
                <DropdownItem
                    v-if="rule.can_toggle"
                    icon="fieldtype-toggle"
                    :text="rule.enabled ? __('webhook-manager::messages.cp.row_disable') : __('webhook-manager::messages.cp.row_enable')"
                    @click="toggle(rule)"
                />
            </template>
        </Listing>

        <DocsCallout :topic="__('webhook-manager::messages.cp.page_rules')" url="https://github.com/goldnead/statamic-webhook-manager#rules" />
    </div>
</template>
