<script setup>
/**
 * Insights / observability dashboard.
 *
 * Layout follows Statamic core's dashboard idiom — native Header, compact
 * StatTile cards in an auto-fit container grid, and a Panel per chart.
 *
 * Each chart sits in a `Card` inside its Panel, not on the Panel itself. The
 * Panel is the grey frame and carries the heading and subheading only; the
 * padding a chart needs lives on `Card` (ui-vocabulary §19). The charts used
 * to be a `<div class="p-6">` straight on the grey, which is the loudest
 * "third-party addon" signal after a hand-rolled button.
 *
 * Charts are self-contained (responsive HTML bars + a single inline-SVG
 * trend line) so the addon ships no charting dependency and inherits the
 * CP's Tailwind tokens for a fully native look in light and dark mode. The
 * inline SVG is a drawn chart, not an icon — §16 is about `icon` props
 * wanting a name from the icon set, which is a different thing.
 */
import { computed } from 'vue';
import { Head } from '@statamic/cms/inertia';
import { router } from '@statamic/cms/inertia';
import {
    Header,
    Card,
    Panel,
    Badge,
    Button,
    Select,
    MiddleEllipsis,
} from '@statamic/cms/ui';
import { DateFormatter, NumberFormatter } from '@statamic/cms';
import StatTile from '../../components/StatTile.vue';

const props = defineProps({
    stats: { type: Object, required: true },
    days: { type: Number, required: true },
    webhookId: { type: [Number, null], default: null },
    rangeOptions: { type: Array, required: true },
    webhookOptions: { type: Array, required: true },
    baseUrl: { type: String, required: true },
    deliveriesUrl: { type: String, required: true },
});

const summary = computed(() => props.stats.summary);
const series = computed(() => props.stats.timeseries ?? []);
const latency = computed(() => props.stats.latency ?? {});
const errors = computed(() => props.stats.errors ?? []);
const topFailing = computed(() => props.stats.top_failing ?? []);

const hasData = computed(() => (summary.value?.total ?? 0) > 0);

// ── Stat tiles ──────────────────────────────────────────────────────────
const tiles = computed(() => [
    { key: 'total', icon: 'upload', label: __('webhook-manager::messages.insights_total_deliveries'), value: fmt(summary.value.total) },
    { key: 'rate', icon: 'checkmark', label: __('webhook-manager::messages.insights_success_rate'), value: summary.value.success_rate + '%' },
    { key: 'failed', icon: 'warning-diamond', label: __('webhook-manager::messages.insights_failed'), value: fmt(summary.value.failed) },
    { key: 'p95', icon: 'time-clock', label: __('webhook-manager::messages.insights_p95_latency'), value: latency.value.p95 != null ? ms(latency.value.p95) : '—' },
]);

// ── Volume bars (success/failed stacked per day) ────────────────────────
const maxTotal = computed(() => Math.max(1, ...series.value.map((d) => d.total)));

// ── Success-rate trend (inline SVG polyline) ────────────────────────────
const trendPoints = computed(() => {
    const pts = series.value
        .map((d, i) => ({ i, rate: d.rate ?? d.success_rate }))
        .filter((p) => p.rate !== null && p.rate !== undefined);
    const n = series.value.length;
    if (pts.length === 0 || n < 2) return '';
    return pts
        .map((p) => `${(p.i / (n - 1)) * 100},${100 - p.rate}`)
        .join(' ');
});
const trendArea = computed(() => {
    if (!trendPoints.value) return '';
    const first = trendPoints.value.split(' ')[0].split(',')[0];
    const last = trendPoints.value.split(' ').slice(-1)[0].split(',')[0];
    return `${first},100 ${trendPoints.value} ${last},100`;
});

// ── Latency bars ────────────────────────────────────────────────────────
const latencyRows = computed(() => {
    const max = Math.max(1, latency.value.max ?? 0);
    return [
        { label: 'p50', value: latency.value.p50 },
        { label: 'p95', value: latency.value.p95 },
        { label: 'p99', value: latency.value.p99 },
        { label: __('webhook-manager::messages.cp.insights_max'), value: latency.value.max },
    ].map((r) => ({ ...r, pct: r.value != null ? Math.max(2, (r.value / max) * 100) : 0 }));
});
const hasLatency = computed(() => latency.value.p50 != null);

// ── Error breakdown ─────────────────────────────────────────────────────
const maxError = computed(() => Math.max(1, ...errors.value.map((e) => e.count)));
function errorLabel(type) {
    return __('webhook-manager::messages.failure_types.' + type) || type;
}

// ── Filters ─────────────────────────────────────────────────────────────
function reload(patch) {
    router.get(props.baseUrl, { days: props.days, webhook: props.webhookId || 'all', ...patch }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

// ── Formatting helpers ──────────────────────────────────────────────────
//
// Numbers and dates go through core's formatters, not through bare `Intl`.
// Statamic lets a user pick a formatting locale in their profile; core's
// DateFormatter/NumberFormatter honour that choice, while
// `new Intl.NumberFormat()` and `toLocaleDateString(undefined, …)` silently
// take whatever locale the browser happens to be set to. Those two calls were
// the only places in this addon that formatted a date or a number itself —
// every other screen already renders through core's `<date-time>`.
function fmt(n) {
    return NumberFormatter.format(n ?? 0);
}
function ms(v) {
    if (v == null) return '—';
    return v >= 1000 ? (v / 1000).toFixed(v >= 10000 ? 0 : 1) + 's' : v + 'ms';
}
function shortDate(iso) {
    return DateFormatter.format(new Date(iso + 'T00:00:00'), { month: 'short', day: 'numeric' });
}
</script>

<template>
    <Head :title="[__('webhook-manager::messages.insights_title'), __('webhook-manager::messages.cp.app_name')]" />

    <Header :title="__('webhook-manager::messages.insights_title')" icon="chart-monitoring-indicator">
        <div class="w-44">
            <Select
                :model-value="webhookId || 'all'"
                :options="webhookOptions"
                @update:model-value="(v) => reload({ webhook: v })"
            />
        </div>
        <div class="w-36">
            <Select
                :model-value="days"
                :options="rangeOptions"
                @update:model-value="(v) => reload({ days: v })"
            />
        </div>
        <Button :href="deliveriesUrl" :text="__('webhook-manager::messages.insights_view_deliveries')" />
    </Header>

    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
        {{ __('webhook-manager::messages.insights_subtitle') }}
    </p>

    <!-- Compact native stat tiles (shared StatTile) -->
    <div class="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-4 mt-4">
        <StatTile
            v-for="tile in tiles"
            :key="tile.key"
            :label="tile.label"
            :value="tile.value"
            :icon="tile.icon"
        />
    </div>

    <template v-if="hasData">
        <!-- Delivery volume -->
        <Panel
            :heading="__('webhook-manager::messages.insights_volume_heading')"
            :subheading="__('webhook-manager::messages.insights_volume_sub')"
            class="mt-6"
        >
            <Card>
                <div class="flex items-end gap-px h-40" role="img" :aria-label="__('webhook-manager::messages.insights_volume_heading')">
                    <div
                        v-for="(d, i) in series"
                        :key="i"
                        class="flex-1 flex flex-col justify-end h-full min-w-px group relative"
                        :title="`${shortDate(d.date)} — ${d.success} ✓ / ${d.failed} ✗`"
                    >
                        <div
                            class="w-full bg-red-400 dark:bg-red-500 rounded-t-sm"
                            :style="{ height: ((d.failed / maxTotal) * 100) + '%' }"
                        />
                        <div
                            class="w-full bg-green-500 dark:bg-green-500"
                            :class="{ 'rounded-t-sm': d.failed === 0 }"
                            :style="{ height: ((d.success / maxTotal) * 100) + '%' }"
                        />
                    </div>
                </div>
                <div class="flex justify-between mt-2 text-xs text-gray-500 dark:text-gray-400 dark:text-gray-400">
                    <span>{{ shortDate(series[0].date) }}</span>
                    <span>{{ shortDate(series[series.length - 1].date) }}</span>
                </div>
                <div class="flex gap-4 mt-3 text-xs text-gray-500 dark:text-gray-400">
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-green-500" /> {{ __('webhook-manager::messages.cp.state_success') }}</span>
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-red-400 dark:bg-red-500" /> {{ __('webhook-manager::messages.cp.state_failed') }}</span>
                </div>
            </Card>
        </Panel>

        <div class="grid grid-cols-[repeat(auto-fit,minmax(320px,1fr))] items-start gap-6 mt-6">
            <!-- Success rate trend -->
            <Panel
                :heading="__('webhook-manager::messages.insights_success_heading')"
                :subheading="__('webhook-manager::messages.insights_success_sub')"
            >
                <Card>
                    <div class="h-40 w-full">
                        <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="h-full w-full overflow-visible">
                            <!-- gridlines at 0/50/100% -->
                            <line v-for="y in [0, 50, 100]" :key="y" x1="0" :y1="y" x2="100" :y2="y"
                                class="stroke-gray-200 dark:stroke-gray-700" stroke-width="0.5" vector-effect="non-scaling-stroke" />
                            <polygon v-if="trendArea" :points="trendArea" class="fill-green-500/10" />
                            <polyline v-if="trendPoints" :points="trendPoints" fill="none"
                                class="stroke-green-500" stroke-width="2" vector-effect="non-scaling-stroke"
                                stroke-linejoin="round" stroke-linecap="round" />
                        </svg>
                    </div>
                    <div class="flex justify-between mt-2 text-xs text-gray-500 dark:text-gray-400 dark:text-gray-400">
                        <span>0%</span><span>100%</span>
                    </div>
                </Card>
            </Panel>

            <!-- Latency -->
            <Panel
                :heading="__('webhook-manager::messages.insights_latency_heading')"
                :subheading="__('webhook-manager::messages.insights_latency_sub')"
            >
                <Card>
                    <div v-if="hasLatency" class="space-y-3">
                        <div v-for="row in latencyRows" :key="row.label" class="flex items-center gap-3">
                            <span class="w-10 text-xs font-medium text-gray-500 dark:text-gray-400 tabular-nums">{{ row.label }}</span>
                            <div class="flex-1 h-5 bg-gray-100 dark:bg-gray-800 rounded">
                                <div class="h-full bg-primary rounded" :style="{ width: row.pct + '%' }" />
                            </div>
                            <span class="w-16 text-right text-xs tabular-nums text-gray-900 dark:text-gray-100">{{ ms(row.value) }}</span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400 dark:text-gray-400 py-8 text-center">{{ __('webhook-manager::messages.insights_no_latency') }}</p>
                </Card>
            </Panel>
        </div>

        <div class="grid grid-cols-[repeat(auto-fit,minmax(320px,1fr))] items-start gap-6 mt-6">
            <!-- Error breakdown -->
            <Panel
                :heading="__('webhook-manager::messages.insights_errors_heading')"
                :subheading="__('webhook-manager::messages.insights_errors_sub')"
            >
                <Card>
                    <div v-if="errors.length" class="space-y-3">
                        <div v-for="e in errors" :key="e.type" class="flex items-center gap-3">
                            <span class="w-28 text-xs text-gray-600 dark:text-gray-300 truncate" :title="errorLabel(e.type)">{{ errorLabel(e.type) }}</span>
                            <div class="flex-1 h-5 bg-gray-100 dark:bg-gray-800 rounded">
                                <div class="h-full bg-red-400 dark:bg-red-500 rounded" :style="{ width: Math.max(4, (e.count / maxError) * 100) + '%' }" />
                            </div>
                            <span class="w-10 text-right text-xs tabular-nums text-gray-900 dark:text-gray-100">{{ e.count }}</span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400 dark:text-gray-400 py-8 text-center">{{ __('webhook-manager::messages.insights_no_failures') }}</p>
                </Card>
            </Panel>

            <!-- Top failing endpoints -->
            <Panel
                :heading="__('webhook-manager::messages.insights_top_failing_heading')"
                :subheading="__('webhook-manager::messages.insights_top_failing_sub')"
            >
                <Card>
                    <ul v-if="topFailing.length" class="divide-y divide-gray-100 dark:divide-gray-800">
                        <li v-for="(row, i) in topFailing" :key="i" class="flex items-center gap-3 py-2 first:pt-0 last:pb-0">
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">{{ row.name || __('webhook-manager::messages.cp.webhook_generic') }}</div>
                                <div class="font-mono text-xs text-gray-500 dark:text-gray-400">
                                    <MiddleEllipsis :text="row.url || ''" />
                                </div>
                            </div>
                            <Badge color="red" :text="String(row.failures)" />
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400 dark:text-gray-400 py-8 text-center">{{ __('webhook-manager::messages.insights_no_failures') }}</p>
                </Card>
            </Panel>
        </div>
    </template>

    <!-- Empty state -->
    <Panel v-else class="mt-6">
        <Card>
            <p class="text-sm text-gray-500 dark:text-gray-400 py-6 text-center">
                {{ __('webhook-manager::messages.insights_empty') }}
            </p>
        </Card>
    </Panel>
</template>
