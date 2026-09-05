<script setup>
import { computed, ref } from 'vue';
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
    // Null on purpose — see the controller. There is no bulk-action endpoint
    // for this listing, and a non-null value here is what makes core draw a
    // checkbox column whose menu can never fill.
    actionUrl: { type: String, default: null },
    listingUrl: { type: String, required: true },
    createUrl: { type: String, required: true },
    canCreate: { type: Boolean, default: false },
});

const isEmpty = computed(
    () => !props.endpoints?.data?.length && !props.endpoints?.meta?.total,
);

const reloadPage = () => router.reload({ only: ['endpoints'] });

// Centralised colour mapping for auth_type badges.
// Kept in Vue (not PHP) so it stays in sync with dark-mode-aware ui.
const authColor = (type) => {
    switch ((type || '').toLowerCase()) {
        case 'none':          return 'default';
        case 'hmac':          return 'green';
        case 'signature':
        case 'static_header':
        case 'bearer':
        case 'basic':         return 'blue';
        case 'ip_allowlist':  return 'purple';
        default:              return 'default';
    }
};

// Centralised colour mapping for action_type badges.
const actionColor = (type) => {
    switch ((type || '').toLowerCase()) {
        case 'noop':            return 'default';
        case 'forward':         return 'blue';
        case 'store':           return 'green';
        case 'notify':          return 'amber';
        case 'rule_trigger':    return 'purple';
        default:                return 'default';
    }
};

// Built in PHP (WebhookManagerServiceProvider::inboundPath) and printed here.
// Not assembled in the browser: this is the string an operator pastes into a
// sender's webhook field, and it has to be the one the router matches.
function fullUrl(endpoint) {
    return endpoint.public_path ?? '';
}

function toggle(endpoint) {
    if (!endpoint.toggle_url) return;
    router.patch(endpoint.toggle_url, {}, {
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
            :action-url="actionUrl"
            :url="listingUrl"
            preferences-prefix="webhook-manager.inbound"
            @refreshing="reloadPage"
        >
            <!-- Name column: link + sub-handle -->
            <template #cell-name="{ row }">
                <div>
                    <Link :href="row.edit_url" class="font-medium">{{ row.name }}</Link>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 font-mono">{{ row.handle }}</div>
                </div>
            </template>

            <!-- Path column: full URL with copy button + MiddleEllipsis -->
            <template #cell-path="{ row }">
                <div class="flex items-center gap-1.5 font-mono text-sm">
                    <MiddleEllipsis
                        :text="fullUrl(row)"
                        :href="fullUrl(row)"
                        class="text-primary"
                    />
                    <button
                        type="button"
                        class="shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition"
                        :title="__('Copy URL')"
                        :aria-label="__('Copy URL')"
                        @click.prevent="$clipboard(fullUrl(row))"
                    >
                        <Icon name="duplicate" class="w-3.5 h-3.5" />
                    </button>
                </div>
            </template>

            <!-- Auth type column.
                 A square chip, not a status: it names which of several auth
                 schemes an endpoint uses, the way a tag does. The status of an
                 endpoint is the Status column, and that badge is a pill with a
                 semantic colour and no `size` (ui-vocabulary §22).

                 The label comes from the server. This used to print
                 `row.auth_type` — `static_header`, `bearer`: the handles the
                 auth registry keys itself by, shown to somebody who never sees
                 that registry (§23). -->
            <template #cell-auth_type="{ row }">
                <Badge
                    :color="authColor(row.auth_type)"
                    :text="row.auth_type_label"
                    size="sm"
                />
            </template>

            <!-- Action type column — same shape, same reasoning. -->
            <template #cell-action_type="{ row }">
                <Badge
                    :color="actionColor(row.action_type)"
                    :text="row.action_type_label"
                    size="sm"
                />
            </template>

            <!-- Status column -->
            <template #cell-enabled="{ row }">
                <Badge
                    :color="row.enabled ? 'green' : 'default'"
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
                    :icon="row.enabled ? 'x' : 'checkmark'"
                    @click="toggle(row)"
                />
            </template>
        </Listing>
    </div>
</template>
