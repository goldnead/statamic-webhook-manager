<script setup>
import { ref } from 'vue';
import { Head, Link } from '@statamic/cms/inertia';
import { Header, Button, Panel, Badge, Alert } from '@statamic/cms/ui';

const props = defineProps({
    delivery: { type: Object, required: true },
    canReplay: { type: Boolean, default: false },
    canViewSensitive: { type: Boolean, default: false },
    replayUrl: { type: String, required: true },
    indexUrl: { type: String, required: true },
});

const replaying = ref(false);
const replayResult = ref(null);

function statusColor(badge) {
    return {
        success: 'green',
        failed: 'red',
        processing: 'amber',
        pending: 'blue',
        cancelled: 'gray',
    }[badge] ?? 'gray';
}

async function replay() {
    if (!props.canReplay) return;
    replaying.value = true;
    replayResult.value = null;
    try {
        const res = await window.axios.post(props.replayUrl, {});
        replayResult.value = res.data;
    } catch (e) {
        replayResult.value = { ok: false, message: e?.response?.data?.message ?? e.message };
    } finally {
        replaying.value = false;
    }
}

function copyCurl() {
    navigator.clipboard.writeText(props.delivery.curl);
}
</script>

<template>
    <Head :title="__('Delivery') + ` #${delivery.id}`" />

    <Header :title="__('Delivery') + ` #${delivery.id}`" icon="audit-checked">
        <Badge :color="statusColor(delivery.status_badge)">{{ delivery.status }}</Badge>
        <div class="flex gap-2">
            <Link :href="indexUrl" class="text-blue self-center">{{ __('Back to deliveries') }}</Link>
            <Button v-if="canReplay" variant="primary" :loading="replaying" @click="replay">
                {{ __('Replay') }}
            </Button>
        </div>
    </Header>

    <Alert v-if="replayResult" :variant="replayResult.ok ? 'success' : 'error'" class="mb-4"
           :text="replayResult.message ?? (__('Replay queued.'))" />

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <Panel :heading="__('Metadata')">
            <dl class="text-sm space-y-2 p-4">
                <div><dt class="inline text-gray-600">{{ __('Trigger') }}:</dt> <dd class="inline"><code>{{ delivery.trigger_type }}</code></dd></div>
                <div><dt class="inline text-gray-600">{{ __('Reference') }}:</dt> <dd class="inline">{{ delivery.trigger_reference ?? '—' }}</dd></div>
                <div><dt class="inline text-gray-600">{{ __('Correlation ID') }}:</dt> <dd class="inline font-mono text-xs">{{ delivery.correlation_id ?? '—' }}</dd></div>
                <div><dt class="inline text-gray-600">{{ __('Attempts') }}:</dt> <dd class="inline">{{ delivery.attempts }}</dd></div>
                <div><dt class="inline text-gray-600">{{ __('Duration') }}:</dt> <dd class="inline">{{ delivery.duration_ms ?? '—' }} ms</dd></div>
                <div><dt class="inline text-gray-600">{{ __('First attempt') }}:</dt> <dd class="inline">{{ delivery.first_attempted_human ?? '—' }}</dd></div>
                <div><dt class="inline text-gray-600">{{ __('Last attempt') }}:</dt> <dd class="inline">{{ delivery.last_attempted_human ?? '—' }}</dd></div>
                <div><dt class="inline text-gray-600">{{ __('Next retry') }}:</dt> <dd class="inline">{{ delivery.next_retry_human ?? '—' }}</dd></div>
                <div v-if="delivery.error_type">
                    <dt class="inline text-gray-600">{{ __('Error') }}:</dt>
                    <dd class="inline">
                        <Badge color="red" size="sm">{{ delivery.error_type }}</Badge>
                        <span v-if="delivery.error_message" class="ml-2">{{ delivery.error_message }}</span>
                    </dd>
                </div>
            </dl>
        </Panel>

        <Panel :heading="__('Request')">
            <dl class="text-sm space-y-1 p-4 mb-2">
                <div><dt class="inline text-gray-600">{{ __('Method') }}:</dt> <dd class="inline"><code>{{ delivery.request_method }}</code></dd></div>
                <div><dt class="inline text-gray-600">{{ __('URL') }}:</dt> <dd class="inline break-all">{{ delivery.request_url }}</dd></div>
            </dl>
            <div class="px-4 pb-4">
                <h4 class="font-semibold text-sm mb-1">{{ __('Headers') }}</h4>
                <pre class="webhook-manager-snapshot-pre">{{ JSON.stringify(delivery.request_headers, null, 2) }}</pre>
                <h4 class="font-semibold text-sm mt-3 mb-1">{{ __('Body') }}</h4>
                <pre class="webhook-manager-snapshot-pre">{{ delivery.request_body }}</pre>
            </div>
        </Panel>
    </div>

    <Panel :heading="`${__('Response')} — HTTP ${delivery.response_status ?? '—'}`" class="mb-4">
        <div class="p-4">
            <h4 class="font-semibold text-sm mb-1">{{ __('Headers') }}</h4>
            <pre class="webhook-manager-snapshot-pre">{{ JSON.stringify(delivery.response_headers, null, 2) }}</pre>
            <h4 class="font-semibold text-sm mt-3 mb-1">{{ __('Body') }}</h4>
            <pre class="webhook-manager-snapshot-pre">{{ delivery.response_body }}</pre>
        </div>
    </Panel>

    <Panel :heading="__('Copy as cURL')">
        <div class="p-4">
            <Alert v-if="!canViewSensitive" variant="warning"
                   :text="__('Sensitive headers and payload keys are masked. Ask an administrator for the “view sensitive payloads” permission to see the original snapshot.')"
                   class="mb-3" />
            <pre class="webhook-manager-snapshot-pre">{{ delivery.curl }}</pre>
            <Button variant="default" size="sm" @click="copyCurl" class="mt-2">{{ __('Copy') }}</Button>
        </div>
    </Panel>
</template>
