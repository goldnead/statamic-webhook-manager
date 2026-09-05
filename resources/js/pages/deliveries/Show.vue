<script setup>
import axios from 'axios';
import { ref, computed } from 'vue';
import { Head } from '@statamic/cms/inertia';
import { router } from '@statamic/cms/inertia';
import {
    Header,
    Button,
    Badge,
    Card,
    Panel,
    Alert,
    Label,
    Table,
    TableRows,
    TableRow,
    TableCell,
    CodeEditor,
} from '@statamic/cms/ui';

/**
 * Delivery detail / debug view.
 *
 * Request and Response sit side by side on lg+ rather than in a PublishForm,
 * because the goal is debugging, not data entry. No tabs — all context is
 * visible at once and the user can scroll.
 *
 * What changed on 05.09.2026 (F33):
 *  - The status badge was in a `#subtitle` slot. `Header` has no such slot
 *    (only `title` and `actions`), so an unknown slot rendered nothing and
 *    said nothing: the single fact this page exists for — did the delivery
 *    succeed? — was nowhere on the screen. It sits next to the title now.
 *  - The facts block was a hand-built 5-column grid with uppercase
 *    letter-spaced micro-labels. Statamic uses neither: labels are `Label`,
 *    and a fact/value block is a table. Both are core components now.
 *  - Every visible string is translated through the addon's own namespace.
 *    They used to be plain `__('Delivery')` etc., which is a *global* JSON
 *    key: `statamic-marketing` defines `"Delivery": "Versand"`, so this
 *    screen was titled "Versand #266" in German — a marketing word for a
 *    webhook delivery, contributed by an unrelated addon.
 *
 * Controller pre-computes `status_color`, `method_color`, `response_code_color`
 * so colour logic stays server-side and consistent with the Index view.
 */
const props = defineProps({
    delivery: { type: Object, required: true },
    replayUrl: { type: String, default: null },
    indexUrl: { type: String, required: true },
});

// ── Replay ──────────────────────────────────────────────────────────────────

const replaying = ref(false);
const lastReplayResult = ref(null);

const canReplay = computed(() => !!props.replayUrl && !!props.delivery.can_replay);

/**
 * The replay endpoint answers with JSON, not with an Inertia response.
 * A `router.post()` therefore could not consume it, fell back to a hard
 * `window.location` visit to the same URL, and dropped the user on a 404
 * for a GET against a POST-only route — while the replay had actually run.
 * A visibly failed replay that in fact succeeded invites a duplicate send,
 * so this goes through axios and reports the real outcome. Afterwards the
 * page is reloaded so the attempt counter and status reflect the new state.
 */
function replay() {
    if (!canReplay.value || replaying.value) return;
    replaying.value = true;
    lastReplayResult.value = null;

    axios
        .post(props.replayUrl, {})
        .then((res) => {
            lastReplayResult.value = { success: res.data?.ok !== false, message: res.data?.message ?? '' };
            router.reload({ only: ['delivery'], preserveScroll: true, preserveState: true });
        })
        .catch((e) => {
            lastReplayResult.value = {
                success: false,
                message: e?.response?.data?.message ?? e.message ?? __('webhook-manager::messages.cp.replay_failed'),
            };
        })
        .finally(() => {
            replaying.value = false;
        });
}

// ── Colour helpers ──────────────────────────────────────────────────────────

/**
 * Delivery status → Statamic Badge colour token.
 *
 * The `??` branch that used to sit here was unreachable: the controller sets
 * `status_color` on every payload it builds, through the same trait the
 * listing uses. It also carried a `retry` status this addon never writes,
 * which is exactly how a dead fallback misleads — it reads like evidence that
 * such a status exists.
 */
const statusColor = computed(() => props.delivery.status_color ?? 'default');

/**
 * Human wording for the status. The raw value is a database enum
 * (`failed`, `success`, …) and read as English in every locale.
 */
const statusLabel = computed(() => {
    const key = `webhook-manager::messages.cp.delivery_status.${props.delivery.status}`;
    const translated = __(key);

    return translated === key ? props.delivery.status : translated;
});

/** HTTP method → Statamic Badge colour token. */
const methodColor = computed(() => props.delivery.method_color ?? ({
    GET:    'blue',
    POST:   'green',
    PUT:    'amber',
    PATCH:  'amber',
    DELETE: 'red',
}[(props.delivery.method || '').toUpperCase()] ?? 'default'));

/** HTTP response code → semantic colour for the Badge. */
const responseCodeColor = computed(() => {
    if (props.delivery.response_code_color) return props.delivery.response_code_color;
    const code = parseInt(props.delivery.response_code, 10);
    if (code >= 500) return 'red';
    if (code >= 400) return 'amber';
    if (code >= 300) return 'blue';
    if (code >= 200) return 'green';
    return 'default';
});

// ── CodeEditor mode detection ───────────────────────────────────────────────

/**
 * Pick a CodeEditor mode from raw body text.
 *
 * Tries to JSON-parse the value; falls back to 'text'. The editor is
 * always read-only here so an incorrect mode just affects highlighting.
 */
function bodyMode(body) {
    if (!body) return 'text';
    try {
        JSON.parse(body);
        return 'json';
    } catch {
        return 'text';
    }
}

/**
 * Look a header up case-insensitively and flatten it to a string.
 *
 * PSR-7 / Guzzle response headers are `{"content-type": ["application/json"]}`
 * — the value is an ARRAY. Calling `.toLowerCase()` on it threw a TypeError
 * that took the whole Response panel down (status code, duration, headers,
 * body), and only on *successful* deliveries, because failed ones have no
 * response headers at all. The panel was missing exactly where one looks.
 */
function headerValue(headers, name) {
    if (!headers || typeof headers !== 'object') return '';
    const wanted = name.toLowerCase();
    const key = Object.keys(headers).find((k) => String(k).toLowerCase() === wanted);
    if (key === undefined) return '';
    const raw = headers[key];
    if (Array.isArray(raw)) return raw.map((v) => String(v)).join(', ');
    if (raw === null || raw === undefined) return '';
    return String(raw);
}

/**
 * Derive CodeEditor mode from a Content-Type header value.
 * Used for the response body which may be HTML, XML, JSON, etc.
 */
function contentTypeMode(headers) {
    const ct = headerValue(headers, 'content-type').toLowerCase();
    if (ct.includes('json'))  return 'json';
    if (ct.includes('xml'))   return 'xml';
    if (ct.includes('html'))  return 'html';
    // No usable Content-Type — fall back to sniffing the body itself so a
    // JSON response still gets highlighted.
    return 'text';
}

// ── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Format an object of headers as pretty JSON for the CodeEditor.
 * Handles both plain objects and null/undefined gracefully.
 */
function headersJson(headers) {
    if (!headers || !Object.keys(headers).length) return '{}';
    try {
        return JSON.stringify(headers, null, 2);
    } catch {
        return '{}';
    }
}

/** Response body highlighting: Content-Type first, body sniffing as fallback. */
function responseMode() {
    const byHeader = contentTypeMode(props.delivery.response?.headers);
    return byHeader === 'text' ? bodyMode(props.delivery.response?.body) : byHeader;
}

const durationText = computed(() =>
    props.delivery.duration_ms != null ? `${props.delivery.duration_ms} ms` : '—'
);

const subjectUrl = computed(() => {
    if (!props.delivery.subject_type || !props.delivery.subject_id) return null;

    return `${props.indexUrl}?subject_type=${encodeURIComponent(props.delivery.subject_type)}`
        + `&subject_id=${encodeURIComponent(props.delivery.subject_id)}`;
});

// ── cURL ────────────────────────────────────────────────────────────────────

/**
 * The controller has always computed a ready-to-paste cURL line
 * (`delivery.curl`) — the template simply never printed it, so the single
 * most useful thing on a debug page was invisible.
 */
const curl = computed(() => props.delivery.curl ?? '');

const curlCopied = ref(false);

function copyCurl() {
    if (!curl.value) return;
    const done = () => {
        curlCopied.value = true;
        setTimeout(() => (curlCopied.value = false), 2000);
    };
    if (navigator?.clipboard?.writeText) {
        navigator.clipboard.writeText(curl.value).then(done).catch(() => {});
        return;
    }
    // Clipboard API needs a secure context; CP installs on plain http
    // would otherwise get a silently dead button.
    const el = document.createElement('textarea');
    el.value = curl.value;
    document.body.appendChild(el);
    el.select();
    try { document.execCommand('copy'); done(); } finally { document.body.removeChild(el); }
}
</script>

<template>
    <Head :title="[__('webhook-manager::messages.cp.delivery'), `#${delivery.id}`, __('webhook-manager::messages.cp.app_name')]" />

    <!-- The narrow detail container ui-vocabulary §2.3 sanctions for detail
         and settings screens (core: pages/forms/Show.vue,
         pages/preferences/Edit.vue). `data-max-width-wrapper` is the part that
         must not be dropped: without it the wrapper ignores the header's
         expand-layout toggle. -->
    <div class="max-w-5xl 3xl:max-w-6xl mx-auto" data-max-width-wrapper>

        <!-- ── Page header ───────────────────────────────────────────
             The status badge lives in the `title` slot. It used to be in a
             `#subtitle` slot, which `Header` does not have — it rendered
             nothing at all, silently. -->
        <Header icon="arrow-up-right">
            <template #title>
                <span class="flex flex-wrap items-center gap-3">
                    {{ __('webhook-manager::messages.cp.delivery') }} #{{ delivery.id }}
                    <Badge :color="statusColor" :text="statusLabel" data-testid="delivery-status" />
                </span>
            </template>

            <Button
                v-if="canReplay"
                :loading="replaying"
                :text="__('webhook-manager::messages.cp.replay')"
                icon="sync"
                @click="replay"
            />
        </Header>

        <!-- ── Replay result feedback ──────────────────────────────────
             `danger` is not one of Alert's variants (default/warning/error/
             success), so a failed replay used to land in $attrs and render in
             the neutral style — the one outcome that has to look wrong looked
             like nothing had happened. -->
        <Alert
            v-if="lastReplayResult"
            :variant="lastReplayResult.success ? 'success' : 'error'"
            :heading="lastReplayResult.success ? __('webhook-manager::messages.cp.replay_ok') : __('webhook-manager::messages.cp.replay_failed')"
            :text="lastReplayResult.message ?? ''"
            class="mb-4"
        />

        <!-- ── Delivery facts ────────────────────────────────────────
             Trigger, subject, attempt counter, duration and correlation ID as
             a two-column table. They are the fields one needs to correlate a
             delivery with a log line; they used to be a bespoke five-column
             grid with uppercase micro-labels, a pattern core uses nowhere. -->
        <Panel :heading="__('webhook-manager::messages.cp.delivery')">
            <Card>
                <!-- No column header: this is a definition table (one fact per
                     row), not a listing of records. A "Metric | Value" header
                     over "Trigger" and "Subject" would only be noise. -->
                <Table>
                    <TableRows>
                        <TableRow>
                            <TableCell class="w-64">{{ __('webhook-manager::messages.cp.col_trigger') }}</TableCell>
                            <TableCell>
                                <Badge color="blue" :text="delivery.trigger_label || delivery.trigger_type || '—'" />
                                <span
                                    v-if="delivery.trigger_reference"
                                    class="block text-2xs text-gray-500 dark:text-gray-400 mt-1 break-all"
                                >{{ delivery.trigger_reference }}</span>
                            </TableCell>
                        </TableRow>

                        <!-- The object the delivery was about; links back to
                             the listing filtered to that object. -->
                        <TableRow data-testid="delivery-subject">
                            <TableCell class="w-64">{{ __('webhook-manager::messages.subject') }}</TableCell>
                            <TableCell>
                                <template v-if="subjectUrl">
                                    <Badge
                                        color="default"
                                        :text="delivery.subject_label || delivery.subject_type"
                                        :href="subjectUrl"
                                    />
                                    <span class="block font-mono text-2xs text-gray-500 dark:text-gray-400 mt-1 break-all">{{ delivery.subject_id }}</span>
                                </template>
                                <span v-else class="text-sm text-gray-500 dark:text-gray-400">—</span>
                            </TableCell>
                        </TableRow>

                        <TableRow>
                            <TableCell class="w-64">{{ __('webhook-manager::messages.cp.attempts') }}</TableCell>
                            <TableCell>
                                <span class="tabular-nums">{{ delivery.attempts ?? '—' }}</span>
                            </TableCell>
                        </TableRow>

                        <TableRow>
                            <TableCell class="w-64">{{ __('webhook-manager::messages.cp.duration') }}</TableCell>
                            <TableCell>
                                <span class="tabular-nums">{{ durationText }}</span>
                            </TableCell>
                        </TableRow>

                        <TableRow>
                            <TableCell class="w-64">{{ __('webhook-manager::messages.cp.correlation_id') }}</TableCell>
                            <TableCell>
                                <code
                                    class="font-mono text-xs break-all text-gray-800 dark:text-gray-200"
                                    data-testid="correlation-id"
                                >{{ delivery.correlation_id || '—' }}</code>
                            </TableCell>
                        </TableRow>
                    </TableRows>
                </Table>
            </Card>
        </Panel>

        <!-- ── Side-by-side Request / Response ──────────────────────── -->
        <!--
            lg+ → 2-column grid (request | response)
            < lg → single column (stacked)
        -->
        <div class="grid lg:grid-cols-2 items-start gap-4 *:min-w-0">

            <!-- Request panel -->
            <Panel :heading="__('webhook-manager::messages.cp.request')">
                <Card>
                    <div class="space-y-4">

                        <div>
                            <Label :text="__('webhook-manager::messages.cp.col_method')" />
                            <Badge :color="methodColor" :text="delivery.method" />
                        </div>

                        <div>
                            <Label :text="__('webhook-manager::messages.cp.col_url')" />
                            <code class="font-mono text-sm break-all text-gray-800 dark:text-gray-200">{{ delivery.url }}</code>
                        </div>

                        <div>
                            <Label :text="__('webhook-manager::messages.cp.headers')" />
                            <CodeEditor
                                mode="json"
                                :model-value="headersJson(delivery.request?.headers)"
                                read-only
                                :rows="6"
                            />
                        </div>

                        <div>
                            <Label :text="__('webhook-manager::messages.cp.body')" />
                            <CodeEditor
                                :mode="bodyMode(delivery.request?.body)"
                                :model-value="delivery.request?.body ?? ''"
                                read-only
                                :rows="14"
                            />
                        </div>
                    </div>
                </Card>
            </Panel>

            <!-- Response panel -->
            <Panel :heading="__('webhook-manager::messages.cp.response')">
                <Card>
                    <div class="space-y-4">

                        <div>
                            <Label :text="__('webhook-manager::messages.cp.status_code')" />
                            <Badge
                                :color="responseCodeColor"
                                :text="String(delivery.response_code ?? '—')"
                            />
                        </div>

                        <div>
                            <Label :text="__('webhook-manager::messages.cp.duration')" />
                            <span class="text-sm tabular-nums text-gray-900 dark:text-gray-100">{{ durationText }}</span>
                        </div>

                        <div>
                            <Label :text="__('webhook-manager::messages.cp.headers')" />
                            <CodeEditor
                                mode="json"
                                :model-value="headersJson(delivery.response?.headers)"
                                read-only
                                :rows="6"
                            />
                        </div>

                        <div>
                            <Label :text="__('webhook-manager::messages.cp.body')" />
                            <CodeEditor
                                :mode="responseMode()"
                                :model-value="delivery.response?.body ?? ''"
                                read-only
                                :rows="14"
                            />
                        </div>
                    </div>
                </Card>
            </Panel>
        </div>

        <!-- ── Timing & Errors (only when error data present) ───────── -->
        <Panel
            v-if="delivery.error || delivery.error_type || delivery.next_retry_at"
            :heading="__('webhook-manager::messages.cp.timing_errors')"
        >
            <Card>
                <Table>
                    <TableRows>
                        <TableRow v-if="delivery.error_type">
                            <TableCell class="w-48">{{ __('webhook-manager::messages.cp.error_type') }}</TableCell>
                            <TableCell>
                                <Badge
                                    :color="delivery.error_type_color ?? 'default'"
                                    :text="delivery.error_type_label ?? delivery.error_type"
                                />
                            </TableCell>
                        </TableRow>

                        <TableRow v-if="delivery.error">
                            <TableCell class="w-48">{{ __('webhook-manager::messages.cp.error_message') }}</TableCell>
                            <TableCell>
                                <span class="text-sm text-red-700 dark:text-red-400 break-words">{{ delivery.error }}</span>
                            </TableCell>
                        </TableRow>

                        <TableRow v-if="delivery.next_retry_at">
                            <TableCell class="w-48">{{ __('webhook-manager::messages.cp.next_retry') }}</TableCell>
                            <TableCell><date-time :of="delivery.next_retry_at" /></TableCell>
                        </TableRow>
                    </TableRows>
                </Table>
            </Card>
        </Panel>

        <!-- ── Payload Snapshot (when stored) ───────────────────────── -->
        <Panel
            v-if="delivery.snapshot"
            :heading="__('webhook-manager::messages.cp.payload_snapshot')"
            :subheading="__('webhook-manager::messages.cp.payload_snapshot_sub')"
        >
            <Card>
                <CodeEditor
                    mode="json"
                    :model-value="typeof delivery.snapshot === 'string'
                        ? delivery.snapshot
                        : JSON.stringify(delivery.snapshot, null, 2)"
                    read-only
                    :rows="12"
                />
            </Card>
        </Panel>

        <!-- ── Reproduce with cURL ──────────────────────────────────── -->
        <Panel
            v-if="curl"
            :heading="__('webhook-manager::messages.cp.reproduce')"
            :subheading="__('webhook-manager::messages.cp.reproduce_sub')"
        >
            <template #header-actions>
                <Button
                    size="sm"
                    icon="duplicate"
                    :text="curlCopied ? __('webhook-manager::messages.cp.copied') : __('webhook-manager::messages.cp.copy')"
                    @click="copyCurl"
                />
            </template>
            <Card>
                <CodeEditor
                    mode="shell"
                    :model-value="curl"
                    read-only
                    :rows="8"
                    data-testid="curl"
                />
            </Card>
        </Panel>

    </div>
</template>
