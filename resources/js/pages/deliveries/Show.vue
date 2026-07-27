<script setup>
import axios from 'axios';
import { ref, computed } from 'vue';
import { Head } from '@statamic/cms/inertia';
import { router } from '@statamic/cms/inertia';
import {
    Header,
    Button,
    Badge,
    Panel,
    Alert,
    CodeEditor,
} from '@statamic/cms/ui';

/**
 * Delivery detail / debug view.
 *
 * Uses a custom two-column layout (Request | Response side-by-side on lg+)
 * rather than PublishForm because the goal is debugging, not data-entry.
 * No tabs — all context is visible at once and the user can scroll.
 *
 * Controller pre-computes `status_color`, `method_color`, `response_code_color`
 * so colour logic stays server-side and consistent with the Index view.
 */
const props = defineProps({
    delivery:   { type: Object, required: true },
    replayUrl:  { type: String, default: null },
    indexUrl:   { type: String, required: true },
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
                message: e?.response?.data?.message ?? e.message ?? 'Replay failed.',
            };
        })
        .finally(() => {
            replaying.value = false;
        });
}

// ── Colour helpers ──────────────────────────────────────────────────────────

/** Delivery status → Statamic Badge colour token. */
const statusColor = computed(() => props.delivery.status_color ?? ({
    success: 'green',
    failed:  'red',
    pending: 'amber',
    retry:   'amber',
}[props.delivery.status] ?? 'default'));

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
    <Head :title="[__('Delivery'), `#${delivery.id}`, __('Webhook Manager')]" />

    <div class="max-w-5xl 3xl:max-w-6xl mx-auto" data-max-width-wrapper>

        <!-- ── Page header ───────────────────────────────────────────── -->
        <Header :title="`${__('Delivery')} #${delivery.id}`" icon="arrow-up-right">
            <template #subtitle>
                <Badge :color="statusColor" :text="delivery.status" />
            </template>

            <Button
                v-if="canReplay"
                :loading="replaying"
                :text="__('Replay')"
                @click="replay"
            />
        </Header>

        <!-- ── Replay result feedback ────────────────────────────────── -->
        <Alert
            v-if="lastReplayResult"
            :variant="lastReplayResult.success ? 'success' : 'danger'"
            :heading="lastReplayResult.success ? __('Replayed successfully') : __('Replay failed')"
            :text="lastReplayResult.message ?? ''"
            class="mt-4"
        />

        <!-- ── Delivery facts ────────────────────────────────────────
             Trigger, correlation ID and the attempt counter used to be
             reachable only by reading the raw request-header JSON, or (for
             attempts) only on failed deliveries. They are the fields one
             actually needs to correlate a delivery with a log line, so they
             get their own panel.
        -->
        <Panel :heading="__('Delivery')" class="mt-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 p-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-1">
                        {{ __('Trigger') }}
                    </p>
                    <Badge color="blue" :text="delivery.trigger_label || delivery.trigger_type || '—'" />
                    <p
                        v-if="delivery.trigger_reference"
                        class="text-2xs text-gray-500 dark:text-gray-400 mt-1 break-all"
                    >
                        {{ delivery.trigger_reference }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-1">
                        {{ __('Attempts') }}
                    </p>
                    <span class="text-sm tabular-nums text-gray-700 dark:text-gray-300">
                        {{ delivery.attempts ?? '—' }}
                    </span>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-1">
                        {{ __('Duration') }}
                    </p>
                    <span class="text-sm tabular-nums text-gray-700 dark:text-gray-300">
                        {{ delivery.duration_ms != null ? `${delivery.duration_ms} ms` : '—' }}
                    </span>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-1">
                        {{ __('Correlation ID') }}
                    </p>
                    <code
                        class="font-mono text-xs break-all text-gray-800 dark:text-gray-200"
                        data-testid="correlation-id"
                    >
                        {{ delivery.correlation_id || '—' }}
                    </code>
                </div>
            </div>
        </Panel>

        <!-- ── Side-by-side Request / Response ──────────────────────── -->
        <!--
            lg+ → 2-column grid (request | response)
            < lg → single column (stacked)
        -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">

            <!-- Request panel -->
            <Panel :heading="__('Request')">
                <div class="space-y-4 p-4">

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-1">
                            {{ __('Method') }}
                        </p>
                        <Badge :color="methodColor" :text="delivery.method" />
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-1">
                            {{ __('URL') }}
                        </p>
                        <code class="font-mono text-sm break-all text-gray-800 dark:text-gray-200">
                            {{ delivery.url }}
                        </code>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-1">
                            {{ __('Headers') }}
                        </p>
                        <CodeEditor
                            mode="json"
                            :model-value="headersJson(delivery.request?.headers)"
                            read-only
                            :rows="6"
                        />
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-1">
                            {{ __('Body') }}
                        </p>
                        <CodeEditor
                            :mode="bodyMode(delivery.request?.body)"
                            :model-value="delivery.request?.body ?? ''"
                            read-only
                            :rows="14"
                        />
                    </div>
                </div>
            </Panel>

            <!-- Response panel -->
            <Panel :heading="__('Response')">
                <div class="space-y-4 p-4">

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-1">
                            {{ __('Status Code') }}
                        </p>
                        <Badge
                            :color="responseCodeColor"
                            :text="String(delivery.response_code ?? '—')"
                        />
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-1">
                            {{ __('Duration') }}
                        </p>
                        <span class="text-sm tabular-nums text-gray-700 dark:text-gray-300">
                            {{ delivery.duration_ms != null ? `${delivery.duration_ms} ms` : '—' }}
                        </span>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-1">
                            {{ __('Headers') }}
                        </p>
                        <CodeEditor
                            mode="json"
                            :model-value="headersJson(delivery.response?.headers)"
                            read-only
                            :rows="6"
                        />
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-1">
                            {{ __('Body') }}
                        </p>
                        <CodeEditor
                            :mode="responseMode()"
                            :model-value="delivery.response?.body ?? ''"
                            read-only
                            :rows="14"
                        />
                    </div>
                </div>
            </Panel>
        </div>

        <!-- ── Timing & Errors (only when error data present) ───────── -->
        <Panel
            v-if="delivery.error || delivery.error_type"
            :heading="__('Timing & Errors')"
            class="mt-4"
        >
            <div class="space-y-3 p-4">

                <div v-if="delivery.error_type" class="flex items-center gap-2">
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 w-28 shrink-0">
                        {{ __('Error Type') }}
                    </p>
                    <Badge
                        :color="delivery.error_type_color ?? 'default'"
                        :text="delivery.error_type_label ?? delivery.error_type"
                    />
                </div>

                <div v-if="delivery.error" class="flex items-start gap-2">
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 w-28 shrink-0 pt-0.5">
                        {{ __('Message') }}
                    </p>
                    <span class="text-sm text-red-700 dark:text-red-400 break-words">
                        {{ delivery.error }}
                    </span>
                </div>

                <div v-if="delivery.next_retry_at" class="flex items-center gap-2">
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 w-28 shrink-0">
                        {{ __('Next Retry') }}
                    </p>
                    <date-time :of="delivery.next_retry_at" class="text-sm" />
                </div>
            </div>
        </Panel>

        <!-- ── Payload Snapshot (when stored) ───────────────────────── -->
        <Panel
            v-if="delivery.snapshot"
            :heading="__('Payload Snapshot')"
            class="mt-4"
        >
            <div class="p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                    {{ __('The original event payload that triggered this delivery, stored at dispatch time.') }}
                </p>
                <CodeEditor
                    mode="json"
                    :model-value="typeof delivery.snapshot === 'string'
                        ? delivery.snapshot
                        : JSON.stringify(delivery.snapshot, null, 2)"
                    read-only
                    :rows="12"
                />
            </div>
        </Panel>

        <!-- ── Reproduce with cURL ──────────────────────────────────── -->
        <Panel v-if="curl" :heading="__('Reproduce')" class="mt-4">
            <div class="p-4">
                <div class="flex items-center justify-between gap-4 mb-2">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ __('The exact request as a cURL command. Masked headers stay masked.') }}
                    </p>
                    <Button
                        size="sm"
                        icon="duplicate"
                        :text="curlCopied ? __('Copied') : __('Copy')"
                        @click="copyCurl"
                    />
                </div>
                <CodeEditor
                    mode="shell"
                    :model-value="curl"
                    read-only
                    :rows="8"
                    data-testid="curl"
                />
            </div>
        </Panel>

    </div>
</template>
