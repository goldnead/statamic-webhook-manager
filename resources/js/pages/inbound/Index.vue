<script setup>
import { computed } from 'vue';
import { Head, Link } from '@statamic/cms/inertia';
import { router } from '@inertiajs/vue3';
import {
    Header,
    Button,
    Badge,
    Icon,
    DropdownItem,
    EmptyStateMenu,
    EmptyStateItem,
    DocsCallout,
    Listing,
    MiddleEllipsis,
    CommandPaletteItem,
} from '@statamic/cms/ui';

/**
 * Inbound endpoint listing.
 *
 * Models the Statamic core "Forms Index" pattern: bifurcate between
 * an EmptyStateMenu and a Listing/Header pair so first-time users see
 * a clear call-to-action while experienced users get the full table
 * with search, sort, filters and bulk actions out of the box.
 */
const props = defineProps({
    endpoints: { type: Object, required: true },
    initialColumns: { type: Array, required: true },
    actionUrl: { type: String, required: true },
    listingUrl: { type: String, required: true },
    createUrl: { type: String, required: true },
    canCreate: { type: Boolean, default: false },
    actionOptions: { type: Object, default: () => ({}) },
    routePrefix: { type: String, default: '' },
});

const isEmpty = computed(
    () => !props.endpoints?.data?.length && !props.endpoints?.meta?.total,
);

const reloadPage = () => router.reload({ only: ['endpoints'] });

// Centralised colour mapping for auth_type badges.
// Kept in Vue (not PHP) so it stays in sync with dark-mode-aware ui.
const authColor = (type) => {
    switch ((type || '').toLowerCase()) {
        case 'none':          return 'gray';
        case 'hmac':          return 'green';
        case 'signature':
        case 'static_header':
        case 'bearer':
        case 'basic':         return 'blue';
        case 'ip_allowlist':  return 'purple';
        default:              return 'gray';
    }
};

// Centralised colour mapping for action_type badges.
const actionColor = (type) => {
    switch ((type || '').toLowerCase()) {
        case 'noop':            return 'gray';
        case 'forward':         return 'blue';
        case 'store':           return 'green';
        case 'notify':          return 'amber';
        case 'rule_trigger':    return 'purple';
        default:                return 'gray';
    }
};

function fullUrl(endpoint) {
    const base = props.routePrefix ? `/${props.routePrefix}` : '';
    return `${base}/${endpoint.path || endpoint.handle}`;
}

function toggle(endpoint) {
    if (!endpoint.toggle_url) return;
    router.patch(endpoint.toggle_url, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="[__('Inbound Endpoints'), __('Webhook Manager')]" />

    <!-- Empty state — shown when no endpoints exist yet -->
    <div v-if="isEmpty" class="max-w-page mx-auto">
        <header class="py-8 pt-16 text-center">
            <h1 class="text-4xl font-bold">{{ __('Inbound Endpoints') }}</h1>
        </header>

        <EmptyStateMenu :heading="__('No inbound endpoints yet')">
            <EmptyStateItem
                v-if="canCreate"
                :href="createUrl"
                icon="download"
                :heading="__('Create Inbound Endpoint')"
                :description="__('Receive and process incoming webhook payloads from external services.')"
            />
        </EmptyStateMenu>

        <DocsCallout topic="Inbound Endpoints" url="https://github.com/goldnead/statamic-webhook-manager" />
    </div>

    <!-- Full listing — shown when at least one endpoint exists -->
    <div v-else class="max-w-page mx-auto">
        <Header :title="__('Inbound Endpoints')" icon="download">
            <template #actions>
                <Button
                    v-if="canCreate"
                    :href="createUrl"
                    :text="__('Create Endpoint')"
                    variant="primary"
                />
                <CommandPaletteItem
                    category="Actions"
                    :text="__('Create Inbound Endpoint')"
                    :url="createUrl"
                />
            </template>
        </Header>

        <Listing
            :items="endpoints"
            :columns="initialColumns"
            :action-url="actionUrl"
            :url="listingUrl"
            preferences-prefix="webhook-manager.inbound"
            @refreshing="reloadPage"
        >
            <!-- Name column: link + sub-handle -->
            <template #cell-name="{ row }">
                <div>
                    <Link :href="row.edit_url" class="font-medium">{{ row.name }}</Link>
                    <div class="text-xs text-gray-500 mt-0.5 font-mono">{{ row.handle }}</div>
                </div>
            </template>

            <!-- Path column: full URL with copy button + MiddleEllipsis -->
            <template #cell-path="{ row }">
                <div class="flex items-center gap-1.5 font-mono text-sm">
                    <MiddleEllipsis
                        :text="fullUrl(row)"
                        :href="fullUrl(row)"
                        class="text-blue-600 dark:text-blue-400"
                    />
                    <button
                        type="button"
                        class="shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition"
                        :title="__('Copy URL')"
                        @click.prevent="$clipboard(fullUrl(row))"
                    >
                        <Icon name="copy" class="w-3.5 h-3.5" />
                    </button>
                </div>
            </template>

            <!-- Auth type column -->
            <template #cell-auth_type="{ row }">
                <Badge
                    :color="authColor(row.auth_type)"
                    :text="row.auth_type || 'none'"
                    size="sm"
                />
            </template>

            <!-- Action type column -->
            <template #cell-action_type="{ row }">
                <Badge
                    :color="actionColor(row.action_type)"
                    :text="actionOptions[row.action_type] || row.action_type || 'noop'"
                    size="sm"
                />
            </template>

            <!-- Status column -->
            <template #cell-enabled="{ row }">
                <Badge
                    :color="row.enabled ? 'green' : 'gray'"
                    :text="row.enabled ? __('Active') : __('Disabled')"
                />
            </template>

            <!-- Row actions -->
            <template #prepended-row-actions="{ row }">
                <DropdownItem
                    v-if="row.can_edit"
                    :text="__('Edit')"
                    :href="row.edit_url"
                    icon="cog"
                />
                <DropdownItem
                    :text="row.enabled ? __('Disable') : __('Enable')"
                    :icon="row.enabled ? 'circle-x' : 'circle-check'"
                    @click="toggle(row)"
                />
            </template>
        </Listing>
    </div>
</template>
