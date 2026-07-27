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
    MiddleEllipsis,
    CommandPaletteItem,
} from '@statamic/cms/ui';

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

/**
 * Enable / disable straight from the row.
 *
 * <Listing> owns its own copy of the rows (it re-fetches them over AJAX), so
 * an Inertia prop reload left the Status badge showing the old value until a
 * full page reload — the row said "Active" while the database already said 0.
 * The row object is reactive, so it is flipped locally the moment the request
 * succeeds, and the listing is asked to re-fetch as well.
 */
function toggle(hook) {
    if (!hook.toggle_url) return;
    router.patch(hook.toggle_url, {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            hook.enabled = !hook.enabled;
            reloadPage();
        },
    });
}

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
</script>

<template>
    <Head :title="[__('Outbound Webhooks'), __('Webhook Manager')]" />

    <div v-if="isEmpty" class="max-w-page mx-auto">
        <header class="py-8 pt-16 text-center">
            <h1 class="text-[25px] font-medium antialiased flex justify-center items-center gap-2 sm:gap-3">
                <Icon name="arrow-up-right" class="size-5 text-gray-500" />
                {{ __('Outbound Webhooks') }}
            </h1>
        </header>

        <EmptyStateMenu :heading="__('webhook-manager::messages.outbound_empty_intro')">
            <EmptyStateItem
                v-if="canCreate && integrationsUrl"
                :href="integrationsUrl"
                icon="flash-bolt-lightning"
                :heading="__('Add an integration')"
                :description="__('Slack, Discord, Zapier and more — pre-configured in a couple of clicks.')"
            />
            <EmptyStateItem
                v-if="canCreate"
                :href="createUrl"
                icon="arrow-up-right"
                :heading="__('Create Outbound Webhook')"
                :description="__('webhook-manager::messages.outbound_create_description')"
            />
        </EmptyStateMenu>

        <DocsCallout :topic="__('Outbound Webhooks')" url="https://statamic.dev/" />
    </div>

    <div v-else class="max-w-page mx-auto">
        <Header :title="__('Outbound Webhooks')" icon="arrow-up-right">
            <Button
                v-if="canCreate && integrationsUrl"
                :href="integrationsUrl"
                :text="__('Add integration')"
                icon="flash-bolt-lightning"
            />
            <CommandPaletteItem
                v-if="canCreate"
                category="Actions"
                :text="__('Create Outbound Webhook')"
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
            :heading="testResult.ok ? __('Test request succeeded') : __('Test request failed')"
            :text="`${testResult.name} — HTTP ${testResult.response_status ?? '—'} — ${testResult.duration_ms ?? '?'}ms${testResult.error_message ? ' — ' + testResult.error_message : ''}`"
            class="mb-4"
            data-testid="outbound-test-result"
        />

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

            <template #cell-url="{ value }">
                <span class="text-gray-700 dark:text-gray-300 font-mono text-xs">
                    <MiddleEllipsis :text="value || ''" />
                </span>
            </template>

            <template #cell-enabled="{ row: hook }">
                <Badge
                    :color="hook.enabled ? 'green' : 'default'"
                    :text="hook.enabled ? __('Active') : __('Disabled')"
                />
            </template>

            <template #prepended-row-actions="{ row: hook }">
                <DropdownItem
                    v-if="hook.can_edit"
                    icon="cog"
                    :text="__('Edit')"
                    :href="hook.edit_url"
                />
                <DropdownItem
                    v-if="hook.can_test && hook.test_url"
                    icon="arrow-up-right"
                    :text="__('Test')"
                    @click="runTest(hook)"
                />
                <DropdownItem
                    v-if="hook.can_toggle"
                    icon="fieldtype-toggle"
                    :text="hook.enabled ? __('Disable') : __('Enable')"
                    @click="toggle(hook)"
                />
            </template>
        </Listing>

        <DocsCallout :topic="__('Outbound Webhooks')" url="https://statamic.dev/" />
    </div>
</template>
