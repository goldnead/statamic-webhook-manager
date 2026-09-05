<script setup>
import axios from 'axios';
import { ref, computed } from 'vue';
import { Head, Link } from '@statamic/cms/inertia';
import { router } from '@statamic/cms/inertia';
import {
    Header,
    Button,
    Badge,
    Icon,
    Alert,
    DropdownItem,
    EmptyStateMenu,
    EmptyStateItem,
    DocsCallout,
    Listing,
    CommandPaletteItem,
} from '@statamic/cms/ui';
import UrlCell from '../../components/UrlCell.vue';

/**
 * Outbound webhook listing.
 *
 * Models the Statamic core "Forms Index" pattern: bifurcate between
 * an EmptyStateMenu and a Listing/Header pair so first-time users see
 * a clear call-to-action while experienced users get the full table
 * with search, sort, filters and bulk actions out of the box.
 */
const props = defineProps({
    webhooks: { type: Object, required: true },
    initialColumns: { type: Array, required: true },
    actionUrl: { type: String, required: true },
    listingUrl: { type: String, required: true },
    createUrl: { type: String, required: true },
    integrationsUrl: { type: String, default: null },
    canCreate: { type: Boolean, default: false },
    triggerOptions: { type: Object, default: () => ({}) },
});

const isEmpty = computed(
    () => !props.webhooks?.data?.length && !props.webhooks?.meta?.total,
);

const reloadPage = () => router.reload({ only: ['webhooks'] });

// Statamic's Badge expects semantic colours. The mapping is centralised
// here (and not duplicated in PHP) so the colours stay in sync with the
// rest of the dark-mode-aware UI.
const methodColor = (method) => {
    switch ((method || '').toUpperCase()) {
        case 'GET':    return 'blue';
        case 'POST':   return 'green';
        case 'PUT':    return 'amber';
        case 'PATCH':  return 'amber';
        case 'DELETE': return 'red';
        default:       return 'default';
    }
};

/*
 * Enable / disable used to be a hand-rolled row action here: a `router.patch()`
 * against `toggle_url` that flipped `hook.enabled` locally, because <Listing>
 * keeps its own copy of the rows and never sees an Inertia prop update.
 *
 * It is gone because on 05.09.2026 the listing got a real action endpoint, and
 * the same job is now done by the native Statamic actions
 * `webhook_manager_enable_outbound` / `_disable_outbound`. Keeping both left
 * two "Disable" entries in one "…" menu, one of them untranslated — a move with
 * only one end. Core's RowActions calls `refresh()` after an action, so the row
 * state that the old comment worried about is handled by the listing itself.
 *
 * The PATCH route stays; it is what the edit screen uses.
 */

// ── Test ────────────────────────────────────────────────────────────────────

const testResult = ref(null);
const testingId = ref(null);

/**
 * The "Test" row action used to be a <DropdownItem :href>, i.e. a plain link —
 * a GET against a POST-only route, so it reliably produced a 404 and never
 * sent anything. It posts via axios now and reports the actual result.
 */
async function runTest(hook) {
    if (!hook.test_url || testingId.value) return;
    testingId.value = hook.id;
    testResult.value = null;
    try {
        const res = await axios.post(hook.test_url, { sample_payload: {} });
        testResult.value = { ...res.data, name: hook.name };
    } catch (e) {
        testResult.value = {
            ok: false,
            name: hook.name,
            error_message: e?.response?.data?.message ?? e.message,
        };
    } finally {
        testingId.value = null;
    }
}

// Rejections from the actions on this listing. Nothing here is a field, so
// whatever comes back is shown in the banner above the rows.
const actionErrors = ref({});
</script>

<template>
    <Head :title="[__('webhook-manager::messages.cp.page_outbound'), __('webhook-manager::messages.cp.app_name')]" />

    <div v-if="isEmpty" class="max-w-page mx-auto">
        <header class="py-8 pt-16 text-center">
            <h1 class="text-[25px] font-medium antialiased flex justify-center items-center gap-2 sm:gap-3">
                <Icon name="arrow-up-right" class="size-5 text-gray-500 dark:text-gray-400" />
                {{ __('webhook-manager::messages.cp.page_outbound') }}
            </h1>
        </header>

        <EmptyStateMenu :heading="__('webhook-manager::messages.outbound_empty_intro')">
            <EmptyStateItem
                v-if="canCreate && integrationsUrl"
                :href="integrationsUrl"
                icon="flash-bolt-lightning"
                :heading="__('webhook-manager::messages.cp.integrations_add_heading')"
                :description="__('webhook-manager::messages.cp.integrations_empty_item_sub')"
            />
            <EmptyStateItem
                v-if="canCreate"
                :href="createUrl"
                icon="arrow-up-right"
                :heading="__('webhook-manager::messages.cp.outbound_create_heading')"
                :description="__('webhook-manager::messages.outbound_create_description')"
            />
        </EmptyStateMenu>

        <DocsCallout :topic="__('webhook-manager::messages.cp.page_outbound')" url="https://github.com/goldnead/statamic-webhook-manager#usage-example" />
    </div>

    <div v-else class="max-w-page mx-auto">
        <Header :title="__('webhook-manager::messages.cp.page_outbound')" icon="arrow-up-right">
            <Button
                v-if="canCreate && integrationsUrl"
                :href="integrationsUrl"
                :text="__('webhook-manager::messages.cp.page_add_integration')"
                icon="flash-bolt-lightning"
            />
            <CommandPaletteItem
                v-if="canCreate"
                category="Actions"
                :text="__('webhook-manager::messages.cp.outbound_create_heading')"
                icon="arrow-up-right"
                :url="createUrl"
                v-slot="{ text, url }"
            >
                <Button :href="url" :text="text" variant="primary" />
            </CommandPaletteItem>
        </Header>

        <Alert
            v-if="testResult"
            :variant="testResult.ok ? 'success' : 'error'"
            :heading="testResult.ok ? __('webhook-manager::messages.cp.test_request_ok_short') : __('webhook-manager::messages.cp.test_request_failed_short')"
            :text="`${testResult.name} — HTTP ${testResult.response_status ?? '—'} — ${testResult.duration_ms ?? '?'}ms${testResult.error_message ? ' — ' + testResult.error_message : ''}`"
            class="mb-4"
            data-testid="outbound-test-result"
        />

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
            preferences-prefix="webhook-manager.outbound"
            push-query
            @refreshing="reloadPage"
        >
            <template #cell-name="{ row: hook }">
                <Link :href="hook.edit_url" class="font-semibold">{{ hook.name }}</Link>
                <span class="block text-2xs text-gray-600 dark:text-gray-400">{{ hook.handle }}</span>
            </template>

            <template #cell-trigger_type="{ row: hook }">
                <Badge color="blue" :text="hook.trigger_label || hook.trigger_type" />
            </template>

            <template #cell-method="{ row: hook }">
                <Badge :color="methodColor(hook.method)" :text="hook.method" />
            </template>

            <!-- `min-w-64` is the part that holds. The `width` percentage in
                 outboundColumns() is only a hint to an auto-layout table, and
                 the URL column is the one that gives way: the Trigger badge
                 does not shrink (a measured 207px at every window width), so
                 below roughly 1280px the URL collapsed back to 119px and
                 `ht...g` — six characters that tell no two rows apart. A floor
                 of 12rem keeps it readable and lets the table scroll instead,
                 which is what core's `.data-table` is built for. -->
            <template #cell-url="{ value }">
                <UrlCell :url="value || ''" class="min-w-64" />
            </template>

            <template #cell-enabled="{ row: hook }">
                <Badge
                    :color="hook.enabled ? 'green' : 'default'"
                    :text="hook.enabled ? __('webhook-manager::messages.cp.status_active') : __('webhook-manager::messages.cp.status_disabled')"
                />
            </template>

            <template #prepended-row-actions="{ row: hook }">
                <DropdownItem
                    v-if="hook.can_edit"
                    icon="cog"
                    :text="__('webhook-manager::messages.cp.action_edit')"
                    :href="hook.edit_url"
                />
                <DropdownItem
                    v-if="hook.can_test && hook.test_url"
                    icon="arrow-up-right"
                    :text="__('webhook-manager::messages.cp.tab_test')"
                    @click="runTest(hook)"
                />
            </template>
        </Listing>

        <DocsCallout :topic="__('webhook-manager::messages.cp.page_outbound')" url="https://github.com/goldnead/statamic-webhook-manager#usage-example" />
    </div>
</template>
