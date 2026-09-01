<script setup>
import { computed, ref } from 'vue';
import { Head } from '@statamic/cms/inertia';
import { router } from '@statamic/cms/inertia';
import {
    Header,
    Badge,
    Alert,
    Button,
    DropdownItem,
    EmptyStateMenu,
    EmptyStateItem,
    DocsCallout,
    Input,
    Listing,
    MiddleEllipsis,
    Select,
} from '@statamic/cms/ui';

/**
 * Delivery listing.
 *
 * Server-driven <Listing> pattern (same as Logs/Index). No create
 * action — deliveries are written by the system. Filters are resolved
 * server-side so the controller stays the single source of truth.
 *
 * error_type vocabulary is identical to LogController so colours/labels
 * are kept in sync below.
 */
const props = defineProps({
    deliveries:     { type: Object, required: true },
    initialColumns: { type: Array,  required: true },
    listingUrl:     { type: String, required: true },
    actionUrl:      { type: String, required: true },
    subjectTypes:   { type: Array,  default: () => [] },
    subjectFilter:  { type: Object, default: () => ({ type: null, id: null }) },
});

// ── Subject filter ──────────────────────────────────────────────────────────
//
// "Which deliveries were about payment 77?" is a question the generic
// <Listing> filters cannot ask, so the two fields live above the listing and
// are resolved server-side like every other filter. The active pair is also
// handed to <Listing> as additional parameters, so its own AJAX refetches
// (sort, page, search) keep the filter instead of silently widening it.
const subjectType = ref(props.subjectFilter?.type ?? null);
const subjectId = ref(props.subjectFilter?.id ?? '');

const subjectFilterActive = computed(
    () => !!(props.subjectFilter?.type || props.subjectFilter?.id),
);

const subjectParameters = computed(() => ({
    subject_type: props.subjectFilter?.type || undefined,
    subject_id: props.subjectFilter?.id || undefined,
}));

const activeSubjectLabel = computed(() => {
    const type = props.subjectFilter?.type;
    const match = props.subjectTypes.find((option) => option.value === type);

    return __('webhook-manager::messages.subject_filter_active', {
        type: match?.label ?? type ?? '',
        id: props.subjectFilter?.id ? `#${props.subjectFilter.id}` : '',
    }).trim();
});

function applySubjectFilter() {
    const query = {};
    if (subjectType.value) query.subject_type = subjectType.value;
    if (String(subjectId.value ?? '').trim() !== '') query.subject_id = String(subjectId.value).trim();

    router.get(props.listingUrl, query, { preserveScroll: true, replace: true });
}

function clearSubjectFilter() {
    subjectType.value = null;
    subjectId.value = '';
    router.get(props.listingUrl);
}

// A filtered view with no hits is still the populated screen, not the
// "nothing dispatched so far" onboarding state.
const isEmpty = computed(
    () => !props.deliveries?.data?.length && !props.deliveries?.meta?.total && !subjectFilterActive.value,
);

const reloadPage = () => router.reload({ only: ['deliveries'] });

// ── Colour helpers ──────────────────────────────────────────────────────────

/** Delivery status → Statamic Badge colour token. */
const statusColor = (status) => ({
    success: 'green',
    failed:  'red',
    pending: 'amber',
    retry:   'amber',
}[status] ?? 'default');

/** HTTP method → Statamic Badge colour token (mirrors Outbound/Index). */
const methodColor = (method) => ({
    GET:    'blue',
    POST:   'green',
    PUT:    'amber',
    PATCH:  'amber',
    DELETE: 'red',
}[(method || '').toUpperCase()] ?? 'default');

/**
 * error_type colour mapping — identical to Logs/Index so the two pages
 * remain visually consistent for operators debugging across both views.
 */
const errorTypeColor = (type) => ({
    network:       'orange',
    timeout:       'amber',
    auth:          'red',
    client:        'yellow',
    server:        'red',
    payload:       'purple',
    configuration: 'blue',
    internal:      'default',
}[type] ?? 'default');

const errorTypeLabel = (type) => ({
    network:       'Network',
    timeout:       'Timeout',
    auth:          'Auth',
    client:        'Client',
    server:        'Server',
    payload:       'Payload',
    configuration: 'Config',
    internal:      'Internal',
}[type] ?? type);

// Rejections from the actions on this listing. Nothing here is a field, so
// whatever comes back is shown in the banner above the rows.
const actionErrors = ref({});

// Was an inline `router.post(row.replay_url, …)` in the template with no error
// branch: a refused replay looked exactly like a successful one.
function replay(row) {
    if (!row.replay_url) return;
    router.post(row.replay_url, {}, {
        preserveScroll: true,
        onError: (errors) => { actionErrors.value = errors || {}; },
        onSuccess: () => { actionErrors.value = {}; },
    });
}
</script>

<template>
    <div>
        <Head :title="[__('Deliveries'), __('Webhook Manager')]" />

        <!-- ── Empty state ─────────────────────────────────────────────── -->
        <div v-if="isEmpty" class="max-w-page mx-auto">
            <Header :title="__('Deliveries')" icon="arrow-up-right" />

            <EmptyStateMenu :heading="__('No deliveries yet')">
                <EmptyStateItem
                    :heading="__('Nothing dispatched so far')"
                    :description="__('Deliveries are recorded automatically when outbound webhooks are fired. Check back once some activity has occurred.')"
                    icon="arrow-up-right"
                />
            </EmptyStateMenu>

            <DocsCallout
                :topic="__('Deliveries')"
                url="https://github.com/goldnead/statamic-webhook-manager#retries"
            />
        </div>

        <!-- ── Populated state ─────────────────────────────────────────── -->
        <div v-else class="max-w-page mx-auto">
            <Header :title="__('Deliveries')" icon="arrow-up-right" />

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

            <!-- Subject filter: which object the deliveries were about. -->
            <div class="flex items-center gap-2 mb-4 flex-wrap" data-testid="subject-filter">
                <!-- Both controls stretch to their parent, so the width sits
                     on a wrapper — on the control itself it is overridden
                     and the row breaks into three lines. -->
                <div class="w-48 shrink-0">
                    <Select
                        v-model="subjectType"
                        :options="subjectTypes"
                        :placeholder="__('webhook-manager::messages.subject_type_placeholder')"
                        clearable
                        size="sm"
                    />
                </div>
                <div class="w-48 shrink-0">
                    <Input
                        v-model="subjectId"
                        :placeholder="__('webhook-manager::messages.subject_id_placeholder')"
                        size="sm"
                        input-class="font-mono"
                        @keydown.enter="applySubjectFilter"
                    />
                </div>
                <Button
                    :text="__('webhook-manager::messages.subject_apply')"
                    variant="primary"
                    size="sm"
                    @click="applySubjectFilter"
                />
                <Button
                    v-if="subjectFilterActive"
                    :text="__('webhook-manager::messages.subject_clear')"
                    variant="ghost"
                    size="sm"
                    @click="clearSubjectFilter"
                />
                <Badge
                    v-if="subjectFilterActive"
                    color="blue"
                    :text="activeSubjectLabel"
                    data-testid="subject-filter-active"
                />
            </div>

            <Listing
                :url="listingUrl"
                :columns="initialColumns"
                :action-url="actionUrl"
                :data="deliveries"
                :additional-parameters="subjectParameters"
                preferences-prefix="webhook-manager.deliveries"
                push-query
                @updated="reloadPage"
            >
                <!-- status column -->
                <template #cell-status="{ row }">
                    <Badge :color="statusColor(row.status)" :text="row.status" />
                </template>

                <!-- subject column — the object the delivery was about -->
                <template #cell-subject="{ row }">
                    <span v-if="row.subject_type && row.subject_id" class="inline-flex items-center gap-1.5 min-w-0">
                        <Badge color="default" :text="row.subject_label || row.subject_type" />
                        <span class="font-mono text-xs text-gray-600 dark:text-gray-400 truncate">{{ row.subject_id }}</span>
                    </span>
                    <span v-else class="text-gray-500 dark:text-gray-400">—</span>
                </template>

                <!-- outbound / trigger name column — links to detail page -->
                <template #cell-outbound_name="{ row }">
                    <a
                        v-if="row.show_url"
                        :href="row.show_url"
                        class="font-semibold hover:text-primary"
                    >{{ row.outbound_name || row.trigger_type || '—' }}</a>
                    <span v-else>{{ row.outbound_name || row.trigger_type || '—' }}</span>
                    <span
                        v-if="row.trigger_type"
                        class="block text-2xs text-gray-500 dark:text-gray-400"
                    >{{ row.trigger_type }}</span>
                </template>

                <!-- url column — mono + middle ellipsis -->
                <template #cell-url="{ row }">
                    <span class="font-mono text-xs text-gray-900 dark:text-gray-100">
                        <MiddleEllipsis :text="row.url || ''" />
                    </span>
                </template>

                <!-- method column -->
                <template #cell-method="{ row }">
                    <Badge :color="methodColor(row.method)" :text="row.method" />
                </template>

                <!-- response_code column -->
                <template #cell-response_code="{ row }">
                    <span class="text-gray-600 dark:text-gray-400 tabular-nums">
                        {{ row.response_code || '—' }}
                    </span>
                </template>

                <!-- attempts column -->
                <template #cell-attempts="{ row }">
                    <span class="text-gray-600 dark:text-gray-400 tabular-nums">
                        {{ row.attempts ?? '—' }}
                    </span>
                </template>

                <!-- error_type column (optional — only shown when column visible) -->
                <template #cell-error_type="{ row }">
                    <Badge
                        v-if="row.error_type"
                        :color="errorTypeColor(row.error_type)"
                        :text="errorTypeLabel(row.error_type)"
                    />
                    <span v-else class="text-gray-500 dark:text-gray-400 dark:text-gray-400">—</span>
                </template>

                <!-- when column -->
                <template #cell-when="{ row }">
                    <date-time :of="row.created_at" />
                </template>

                <!-- row actions -->
                <template #prepended-row-actions="{ row }">
                    <DropdownItem
                        icon="eye"
                        :text="__('View')"
                        :href="row.show_url"
                    />
                    <DropdownItem
                        v-if="row.can_replay"
                        icon="sync"
                        :text="__('Replay')"
                        @click="replay(row)"
                    />
                </template>
            </Listing>

            <DocsCallout
                :topic="__('Deliveries')"
                url="https://github.com/goldnead/statamic-webhook-manager#retries"
            />
        </div>
    </div>
</template>
