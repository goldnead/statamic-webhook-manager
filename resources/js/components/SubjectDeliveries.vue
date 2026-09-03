<script setup>
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import { router } from '@statamic/cms/inertia';
import {
    Alert,
    Badge,
    Button,
    Card,
    MiddleEllipsis,
    Panel,
    Skeleton,
    Table,
    TableCell,
    TableColumn,
    TableColumns,
    TableRow,
    TableRows,
    Text,
} from '@statamic/cms/ui';

/**
 * The webhook deliveries recorded for one object, for another addon's page.
 *
 * Registered globally as `webhook-deliveries-for-subject`, so a payment or
 * offer screen can embed it without importing anything from this package:
 *
 *     <webhook-deliveries-for-subject subject-type="payment" :subject-id="payment.id" />
 *
 * Reads through the CP's `deliveries/for-subject` endpoint, which applies the
 * viewer's `view webhook deliveries` permission and brand scope; the
 * component adds nothing to that. Replay goes through the Inertia router
 * like the listing does, so the progress bar and flash toast are core's.
 */
const props = defineProps({
    subjectType: { type: String, required: true },
    subjectId: { type: [String, Number], required: true },
    // `cpRoot` is what JavascriptComposer hands the CP (`/cp` by default);
    // outside the CP — a test process — there is no `Statamic` global.
    url: {
        type: String,
        default: () => (globalThis.Statamic?.$config?.get('cpRoot') ?? '/cp') + '/webhook-manager/deliveries/for-subject',
    },
    listingUrl: { type: String, default: null },
    limit: { type: Number, default: 10 },
});

const rows = ref([]);
const total = ref(0);
const loading = ref(true);
const error = ref(null);

const params = computed(() => ({
    subject_type: props.subjectType,
    subject_id: String(props.subjectId),
    limit: props.limit,
}));

const allUrl = computed(() => {
    const base = props.listingUrl ?? props.url.replace(/\/for-subject\/?$/, '');
    const query = new URLSearchParams({
        subject_type: props.subjectType,
        subject_id: String(props.subjectId),
    });

    return `${base}?${query}`;
});

function load() {
    loading.value = true;
    error.value = null;

    return axios
        .get(props.url, { params: params.value })
        .then((res) => {
            rows.value = res.data?.data ?? [];
            total.value = res.data?.total ?? rows.value.length;
        })
        .catch((e) => {
            error.value = e?.response?.data?.message ?? e?.message ?? __('Something went wrong');
        })
        .finally(() => {
            loading.value = false;
        });
}

function replay(row) {
    if (!row.replay_url) return;

    router.post(row.replay_url, {}, {
        preserveScroll: true,
        onSuccess: load,
    });
}

const statusColor = (status) => ({
    success: 'green',
    failed: 'red',
    pending: 'amber',
    retry: 'amber',
    processing: 'blue',
}[status] ?? 'default');

const methodColor = (method) => ({
    GET: 'blue',
    POST: 'green',
    PUT: 'amber',
    PATCH: 'amber',
    DELETE: 'red',
}[(method || '').toUpperCase()] ?? 'default');

onMounted(load);
</script>

<template>
    <Panel :heading="__('webhook-manager::messages.subject_deliveries_heading')">
        <template #header-actions>
            <Button
                v-if="total > rows.length"
                :href="allUrl"
                :text="__('webhook-manager::messages.subject_deliveries_all')"
                variant="ghost"
                size="xs"
            />
        </template>

        <Card>
            <div v-if="loading" class="space-y-2" data-testid="subject-deliveries-loading">
                <Skeleton class="h-5 w-full" />
                <Skeleton class="h-5 w-full" />
                <Skeleton class="h-5 w-2/3" />
            </div>

            <Alert
                v-else-if="error"
                variant="error"
                :text="error"
                data-testid="subject-deliveries-error"
            />

            <Text
                v-else-if="!rows.length"
                as="p"
                variant="subtle"
                size="sm"
                :text="__('webhook-manager::messages.subject_deliveries_empty')"
                data-testid="subject-deliveries-empty"
            />

            <Table v-else data-testid="subject-deliveries-table">
                <TableColumns>
                    <TableColumn>{{ __('When') }}</TableColumn>
                    <TableColumn>{{ __('URL') }}</TableColumn>
                    <TableColumn>{{ __('Status') }}</TableColumn>
                    <TableColumn>{{ __('Code') }}</TableColumn>
                    <TableColumn />
                </TableColumns>
                <TableRows>
                    <TableRow v-for="row in rows" :key="row.id" data-testid="subject-delivery-row">
                        <TableCell class="whitespace-nowrap">
                            <a v-if="row.show_url" :href="row.show_url" class="hover:text-primary">
                                <date-time :of="row.created_at" />
                            </a>
                            <date-time v-else :of="row.created_at" />
                        </TableCell>
                        <TableCell>
                            <span class="flex items-center gap-2 min-w-0">
                                <!-- A chip, not a status: the HTTP verb of the
                                     request, sitting in front of its URL the
                                     way a tag sits in front of a title. Square
                                     is right for it; the pill is reserved for
                                     the status badge in the next cell
                                     (ui-vocabulary §22). -->
                                <Badge :color="methodColor(row.method)" :text="row.method" size="sm" />
                                <span class="font-mono text-xs text-gray-900 dark:text-gray-100 min-w-0">
                                    <MiddleEllipsis :text="row.url || ''" />
                                </span>
                            </span>
                        </TableCell>
                        <TableCell>
                            <Badge :color="statusColor(row.status)" :text="row.status" />
                        </TableCell>
                        <TableCell>
                            <span class="text-gray-600 dark:text-gray-400 tabular-nums">
                                {{ row.response_code || '—' }}
                            </span>
                        </TableCell>
                        <TableCell class="text-end">
                            <Button
                                v-if="row.can_replay"
                                size="xs"
                                variant="ghost"
                                icon="sync"
                                :text="__('Replay')"
                                data-testid="subject-delivery-replay"
                                @click="replay(row)"
                            />
                        </TableCell>
                    </TableRow>
                </TableRows>
            </Table>
        </Card>
    </Panel>
</template>
