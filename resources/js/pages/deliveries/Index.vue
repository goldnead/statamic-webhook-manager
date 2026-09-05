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
    Select,
} from '@statamic/cms/ui';
import UrlCell from '../../components/UrlCell.vue';

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
//
// Status colour and wording used to be a second, English copy right here
// (`failed`, `retry` — a status this addon never writes). Both now arrive on
// the row from PresentsDeliveryStatuses, so there is one vocabulary for a
// delivery's status instead of one per screen.

/** HTTP method → Statamic Badge colour token (mirrors Outbound/Index). */
const methodColor = (method) => ({
    GET:    'blue',
    POST:   'green',
    PUT:    'amber',
    PATCH:  'amber',
    DELETE: 'red',
}[(method || '').toUpperCase()] ?? 'default');

// The error type's wording and colour arrive on the row as well
// (PresentsDeliveryErrors). The English map that used to sit here said
// "Config" where the detail page said "Konfigurationsfehler".

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
        <Head :title="[__('webhook-manager::messages.cp.page_deliveries'), __('webhook-manager::messages.cp.app_name')]" />

        <!-- ── Empty state ─────────────────────────────────────────────── -->
        <div v-if="isEmpty" class="max-w-page mx-auto">
            <Header :title="__('webhook-manager::messages.cp.page_deliveries')" icon="arrow-up-right" />

            <EmptyStateMenu :heading="__('webhook-manager::messages.cp.deliveries_empty_heading')">
                <EmptyStateItem
                    :heading="__('webhook-manager::messages.cp.deliveries_empty_item')"
                    :description="__('webhook-manager::messages.cp.deliveries_empty_sub')"
                    icon="arrow-up-right"
                />
            </EmptyStateMenu>

            <DocsCallout
                :topic="__('webhook-manager::messages.cp.page_deliveries')"
                url="https://github.com/goldnead/statamic-webhook-manager#retries"
            />
        </div>

        <!-- ── Populated state ─────────────────────────────────────────── -->
        <div v-else class="max-w-page mx-auto">
            <Header :title="__('webhook-manager::messages.cp.page_deliveries')" icon="arrow-up-right" />

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
                <!-- status column — the wording and the colour both come from
                     the server (PresentsDeliveryStatuses), so this listing says
                     "Fehlgeschlagen" where the detail page one click away says
                     "Fehlgeschlagen", instead of the raw enum `failed`. -->
                <template #cell-status="{ row }">
                    <Badge :color="row.status_color || 'default'" :text="row.status_label || row.status" />
                </template>

                <!-- subject column — the object the delivery was about.
                     The id is a UUID, and an auto-layout table gives a cell
                     whatever its longest unbroken string demands: measured at
                     386px on 05.09.2026, a quarter of the table for a value
                     nobody reads character by character, while the URL column
                     starved next to it. `max-w-40` caps it; `truncate` needs a
                     bounded, `min-w-0` box to bite, and the whole id stays
                     available on hover and in the row's detail page. -->
                <template #cell-subject="{ row }">
                    <span v-if="row.subject_type && row.subject_id" class="flex items-center gap-1.5 max-w-32">
                        <Badge color="default" :text="row.subject_label || row.subject_type" />
                        <span
                            class="min-w-0 truncate font-mono text-xs text-gray-600 dark:text-gray-400"
                            :title="row.subject_id"
                        >{{ row.subject_id }}</span>
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

                <!-- url column — identifying tail first, host and leading path
                     underneath.
                     `min-w-56` is the only thing that holds this column open.
                     A `width` in the column definition does nothing: measured
                     on 05.09.2026, Statamic 6.31's <Listing> puts it neither on
                     the <th>, nor the <td>, nor a <colgroup>, and the table is
                     `table-layout: auto`, so a cell gets what its content asks
                     for. Without a floor this one asked for 60px and every row
                     read `...`. -->
                <template #cell-url="{ row }">
                    <UrlCell :url="row.url || ''" class="min-w-56 max-w-56" />
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
                        :color="row.error_type_color || 'default'"
                        :text="row.error_type_label || row.error_type"
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
                        :text="__('webhook-manager::messages.cp.row_view')"
                        :href="row.show_url"
                    />
                    <DropdownItem
                        v-if="row.can_replay"
                        icon="sync"
                        :text="__('webhook-manager::messages.cp.replay')"
                        @click="replay(row)"
                    />
                </template>
            </Listing>

            <DocsCallout
                :topic="__('webhook-manager::messages.cp.page_deliveries')"
                url="https://github.com/goldnead/statamic-webhook-manager#retries"
            />
        </div>
    </div>
</template>
