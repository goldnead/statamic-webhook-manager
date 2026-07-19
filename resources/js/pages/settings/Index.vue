<script setup>
import { ref, computed } from 'vue';
import { Head } from '@statamic/cms/inertia';
import { useForm } from '@statamic/cms/inertia';
import {
    Header,
    Alert,
    Badge,
    Button,
    Icon,
    Field,
    Input,
    Textarea,
    Select,
    Switch,
    Card,
    CodeEditor,
    Panel,
    ConfirmationModal,
} from '@statamic/cms/ui';

/**
 * Settings page — read-only v1.
 *
 * Config is sourced from config/webhook-manager.php (a static PHP file).
 * In v1 there is no DB-Settings-layer yet, so the form is intentionally
 * read-only. The Alert informs the user where to make changes.
 *
 * Tab layout mirrors the config file sections:
 *   General  — features flags, queue
 *   Defaults — retry, http
 *   Security — inbound security, signature headers
 *   Logging  — delivery logging, pruning
 */
const props = defineProps({
    /** Structured config split per tab (from SettingsController::extractConfig()) */
    config: { type: Object, required: true },
    /** JSON-encoded full config tree for the raw-config panel */
    rawConfig: { type: String, required: true },
    /** Absolute path to the config file on disk */
    configFilePath: { type: String, required: true },
    /** v1: always false; flip to true once a DB-settings-layer exists */
    isEditable: { type: Boolean, default: false },
    /** Active storage driver + record counts + switch URL (SettingsController) */
    storage: { type: Object, required: true },
});

const copied = ref(false);

// ----- storage driver switch -----------------------------------------

const showSwitch = ref(false);

// Target driver to switch to (the other store). Uses form.submit('post', url)
// — the proven submission helper in this Inertia build (the dynamic-verb
// form.post()/router.post() helpers misbehave here, same as the edit forms).
const switchForm = useForm({ driver: props.storage.target });

const storageCountsLine = computed(() => {
    const c = props.storage.counts || {};
    return __('webhook-manager::messages.storage_counts_line', {
        outbound: c['outbound webhooks'] ?? 0,
        inbound: c['inbound endpoints'] ?? 0,
        rules: c['rules'] ?? 0,
        templates: c['templates'] ?? 0,
    });
});

function switchStorage() {
    switchForm.submit('post', props.storage.switch_url, {
        preserveScroll: true,
        onFinish: () => { showSwitch.value = false; },
    });
}

function copyPath() {
    navigator.clipboard.writeText(props.configFilePath).then(() => {
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    });
}

// ----- helpers --------------------------------------------------------

/** Turn an array like ['sha256', 'sha512'] into a readable string */
const arrayToString = (val) => {
    if (Array.isArray(val)) return val.join(', ');
    return val ?? '';
};

/** Render a status-code array as a compact string */
const statusCodesToString = (val) => {
    if (Array.isArray(val)) return val.join(', ');
    return val ?? '';
};
</script>

<template>
    <Head :title="[__('Settings'), __('Webhook Manager')]" />

    <div class="max-w-page mx-auto">

        <!-- ── Page header ──────────────────────────────────────────── -->
        <Header :title="__('Webhook Manager Settings')" icon="sliders-horizontal" />

        <!-- ── Config-file notice ───────────────────────────────────── -->
        <Alert variant="info" class="mb-6">
            <template #default>
                <span>
                    {{ __('These settings are managed in') }}
                    <code class="font-mono text-sm bg-gray-100 dark:bg-dark-700 rounded px-1">{{ configFilePath }}</code>.
                    {{ __('Edit that file to change retry policy, logging mode, masking rules, route prefixes, etc.') }}
                </span>
                <Button
                    size="sm"
                    variant="default"
                    class="ml-3 shrink-0"
                    :text="copied ? __('Copied!') : __('Copy path')"
                    @click="copyPath"
                >
                    <template #icon>
                        <Icon :name="copied ? 'check' : 'clipboard'" />
                    </template>
                </Button>
            </template>
        </Alert>

        <div class="space-y-6">

            <!-- ── Storage driver (interactive) ─────────────────────── -->
                <Panel :heading="__('webhook-manager::messages.storage_heading')" :subheading="__('webhook-manager::messages.storage_sub')">
                    <Card>
                        <Field inline
                            :label="__('webhook-manager::messages.storage_active_driver')"
                        >
                            <div class="flex items-center gap-2">
                                <Badge
                                    :color="storage.driver === 'flat' ? 'green' : 'blue'"
                                    :text="storage.driver_label"
                                />
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ storage.source === 'control_panel'
                                        ? __('webhook-manager::messages.storage_source_control_panel')
                                        : __('webhook-manager::messages.storage_source_config') }}
                                </span>
                            </div>
                        </Field>

                        <Field v-if="storage.driver === 'flat'" inline
                            :label="__('webhook-manager::messages.storage_flat_path_label')"
                        >
                            <Input :model-value="storage.flat_path" read-only class="font-mono text-sm" />
                        </Field>

                        <Field inline
                            :label="__('webhook-manager::messages.storage_records')"
                        >
                            <span class="text-sm text-gray-700 dark:text-gray-300 tabular-nums">{{ storageCountsLine }}</span>
                        </Field>

                        <Field inline
                            :label="__('webhook-manager::messages.storage_switch_to', { driver: storage.target_label })"
                            :instructions="__('webhook-manager::messages.storage_switch_hint', { driver: storage.target_label })"
                        >
                            <Button
                                :text="__('webhook-manager::messages.storage_switch_to', { driver: storage.target_label })"
                                :disabled="switchForm.processing"
                                @click="showSwitch = true"
                            />
                        </Field>
                    </Card>
                </Panel>

            <!-- ── General ──────────────────────────────────────────── -->
                <Panel :heading="__('Features')">
                    <Card>
                    <Field inline
                        :label="__('Outbound webhooks')"
                        :instructions="__('Enable the outbound webhook engine.')"
                    >
                        <Switch
                            :model-value="config.general.features_outbound"
                            :disabled="true"
                        />
                    </Field>

                    <Field inline
                        :label="__('Inbound webhooks')"
                        :instructions="__('Enable the inbound webhook receiver.')"
                    >
                        <Switch
                            :model-value="config.general.features_inbound"
                            :disabled="true"
                        />
                    </Field>

                    <Field inline
                        :label="__('Rules engine')"
                        :instructions="__('Enable the rule/routing engine.')"
                    >
                        <Switch
                            :model-value="config.general.features_rules"
                            :disabled="true"
                        />
                    </Field>

                    <Field inline
                        :label="__('Templates')"
                        :instructions="__('Enable payload template library.')"
                    >
                        <Switch
                            :model-value="config.general.features_templates"
                            :disabled="true"
                        />
                    </Field>

                    <Field inline
                        :label="__('Debug tools')"
                        :instructions="__('Enable the Debug section in the CP.')"
                    >
                        <Switch
                            :model-value="config.general.features_debug_tools"
                            :disabled="true"
                        />
                    </Field>
                </Card>
                </Panel>

                <Panel :heading="__('Queue')">
                    <Card>
                    <Field inline
                        :label="__('Queue connection')"
                        :instructions="__('WEBHOOK_MANAGER_QUEUE_CONNECTION env var. Leave empty to use the default connection.')"
                    >
                        <Input
                            :model-value="config.general.queue_connection ?? ''"
                            :placeholder="__('(default connection)')"
                            read-only
                        />
                    </Field>

                    <Field inline
                        :label="__('Queue name')"
                        :instructions="__('WEBHOOK_MANAGER_QUEUE_NAME env var.')"
                    >
                        <Input
                            :model-value="config.general.queue_name"
                            read-only
                        />
                    </Field>

                    <Field inline
                        :label="__('Sync in console')"
                        :instructions="__('Process jobs synchronously when running via CLI (testing only).')"
                    >
                        <Switch
                            :model-value="config.general.queue_sync_in_console"
                            :disabled="true"
                        />
                    </Field>
                </Card>
                </Panel>

            <!-- ── Defaults ─────────────────────────────────────────── -->
                <Panel :heading="__('Retry defaults')">
                    <Card>
                    <Field inline
                        :label="__('Strategy')"
                        :instructions="__('none | linear | exponential')"
                    >
                        <Input :model-value="config.defaults.retry_strategy" read-only />
                    </Field>

                    <Field inline
                        :label="__('Max attempts')"
                        :instructions="__('Total delivery attempts including the first try.')"
                    >
                        <Input :model-value="String(config.defaults.retry_max_attempts)" read-only />
                    </Field>

                    <Field inline
                        :label="__('Base delay (seconds)')"
                        :instructions="__('Initial backoff delay for linear/exponential strategies.')"
                    >
                        <Input :model-value="String(config.defaults.retry_base_delay_seconds)" read-only />
                    </Field>

                    <Field inline
                        :label="__('Max delay (seconds)')"
                        :instructions="__('Upper cap for exponential backoff.')"
                    >
                        <Input :model-value="String(config.defaults.retry_max_delay_seconds)" read-only />
                    </Field>

                    <Field inline
                        :label="__('Retry on HTTP status codes')"
                        :instructions="__('Comma-separated list of status codes that trigger a retry.')"
                    >
                        <Input :model-value="statusCodesToString(config.defaults.retry_on_status)" read-only />
                    </Field>

                    <Field inline :label="__('Retry on network errors')">
                        <Switch
                            :model-value="config.defaults.retry_on_network_errors"
                            :disabled="true"
                        />
                    </Field>
                </Card>
                </Panel>

                <Panel :heading="__('HTTP defaults')">
                    <Card>
                    <Field inline
                        :label="__('Timeout (seconds)')"
                        :instructions="__('Total request timeout.')"
                    >
                        <Input :model-value="String(config.defaults.http_timeout_seconds)" read-only />
                    </Field>

                    <Field inline
                        :label="__('Connect timeout (seconds)')"
                        :instructions="__('TCP/TLS connection timeout.')"
                    >
                        <Input :model-value="String(config.defaults.http_connect_timeout_seconds)" read-only />
                    </Field>

                    <Field inline :label="__('Follow redirects')">
                        <Switch
                            :model-value="config.defaults.http_follow_redirects"
                            :disabled="true"
                        />
                    </Field>

                    <Field inline :label="__('Max redirects')">
                        <Input :model-value="String(config.defaults.http_max_redirects)" read-only />
                    </Field>

                    <Field inline
                        :label="__('User agent')"
                        :instructions="__('Sent in the User-Agent header of every outbound request.')"
                    >
                        <Input :model-value="config.defaults.http_user_agent" read-only />
                    </Field>

                    <Field inline :label="__('Verify SSL')">
                        <Switch
                            :model-value="config.defaults.http_verify_ssl"
                            :disabled="true"
                        />
                    </Field>
                </Card>
                </Panel>

            <!-- ── Reliability & alerts ─────────────────────────────── -->
                <Panel :heading="__('Circuit breaker')">
                    <Card>
                    <Field inline
                        :label="__('Enabled')"
                        :instructions="__('Auto-disable a webhook after too many consecutive failures.')"
                    >
                        <Switch :model-value="config.reliability.circuit_breaker_enabled" :disabled="true" />
                    </Field>

                    <Field inline
                        :label="__('Failure threshold')"
                        :instructions="__('Consecutive terminal failures before a webhook is auto-disabled. 0 = never.')"
                    >
                        <Input :model-value="String(config.reliability.circuit_breaker_threshold)" read-only />
                    </Field>
                </Card>
                </Panel>

                <Panel :heading="__('Failure alerts')">
                    <Card>
                    <Field inline
                        :label="__('Enabled')"
                        :instructions="__('Notify an admin when a delivery fails after all retries.')"
                    >
                        <Switch :model-value="config.reliability.alerts_enabled" :disabled="true" />
                    </Field>

                    <Field inline
                        :label="__('Throttle (minutes)')"
                        :instructions="__('Minimum minutes between alerts for the same webhook.')"
                    >
                        <Input :model-value="String(config.reliability.alerts_throttle_minutes)" read-only />
                    </Field>

                    <Field inline
                        :label="__('Email alerts')"
                        :instructions="__('WEBHOOK_MANAGER_ALERT_EMAILS env var (comma-separated).')"
                    >
                        <Switch :model-value="config.reliability.alerts_mail_enabled" :disabled="true" />
                    </Field>

                    <Field inline
                        :label="__('Recipients')"
                        :instructions="__('Email addresses that receive failure alerts.')"
                    >
                        <Input
                            :model-value="config.reliability.alerts_mail_recipients"
                            :placeholder="__('(none configured)')"
                            read-only
                        />
                    </Field>

                    <Field inline
                        :label="__('Slack/Discord alert webhook')"
                        :instructions="__('WEBHOOK_MANAGER_ALERT_SLACK_URL env var. Posts failure alerts to a chat channel.')"
                    >
                        <Badge
                            :color="config.reliability.alerts_slack_configured ? 'green' : 'default'"
                            :text="config.reliability.alerts_slack_configured ? __('Configured') : __('Not set')"
                        />
                    </Field>
                </Card>
                </Panel>

            <!-- ── Security ─────────────────────────────────────────── -->
                <Panel :heading="__('Inbound route')">
                    <Card>
                    <Field inline
                        :label="__('Route prefix')"
                        :instructions="__('URL prefix for all inbound webhook endpoints.')"
                    >
                        <Input :model-value="config.security.inbound_route_prefix" read-only />
                    </Field>

                    <Field inline
                        :label="__('Max payload (KB)')"
                        :instructions="__('Requests larger than this value are rejected with 413.')"
                    >
                        <Input :model-value="String(config.security.inbound_max_payload_kb)" read-only />
                    </Field>

                    <Field inline
                        :label="__('Rate limit (per minute)')"
                        :instructions="__('Per-endpoint rate limit. 0 = unlimited.')"
                    >
                        <Input :model-value="String(config.security.inbound_rate_limit_per_minute)" read-only />
                    </Field>

                    <Field inline
                        :label="__('Replay protection TTL (seconds)')"
                        :instructions="__('Inbound requests with a timestamp older than this are rejected.')"
                    >
                        <Input :model-value="String(config.security.inbound_replay_protection_ttl_seconds)" read-only />
                    </Field>
                </Card>
                </Panel>

                <Panel :heading="__('Signature &amp; HMAC')">
                    <Card>
                    <Field inline
                        :label="__('Allowed hash algorithms')"
                        :instructions="__('Algorithms available when generating or verifying signatures.')"
                    >
                        <Input :model-value="arrayToString(config.security.hash_algorithms)" read-only />
                    </Field>

                    <Field inline :label="__('Default hash algorithm')">
                        <Input :model-value="config.security.default_hash_algorithm" read-only />
                    </Field>

                    <Field inline
                        :label="__('Signature header')"
                        :instructions="__('HTTP header name used to transmit the HMAC signature.')"
                    >
                        <Input :model-value="config.security.signature_header" read-only />
                    </Field>

                    <Field inline
                        :label="__('Timestamp header')"
                        :instructions="__('HTTP header name used to transmit the request timestamp.')"
                    >
                        <Input :model-value="config.security.timestamp_header" read-only />
                    </Field>

                    <Field inline
                        :label="__('Timestamp tolerance (seconds)')"
                        :instructions="__('Outbound: how far the remote clock may drift before the signature is rejected.')"
                    >
                        <Input :model-value="String(config.security.timestamp_tolerance_seconds)" read-only />
                    </Field>

                    <Field inline
                        :label="__('Mask secrets in UI')"
                        :instructions="__('Replaces secret values with *** in the CP.')"
                    >
                        <Switch
                            :model-value="config.security.mask_secrets_in_ui"
                            :disabled="true"
                        />
                    </Field>
                </Card>
                </Panel>

            <!-- ── Logging ──────────────────────────────────────────── -->
                <Panel :heading="__('Delivery logging')">
                    <Card>
                    <Field inline
                        :label="__('Log body mode')"
                        :instructions="__('full — store entire body · partial — store first N bytes · none — skip body storage.')"
                    >
                        <Input :model-value="config.logging.mode" read-only />
                    </Field>

                    <Field inline
                        :label="__('Partial bytes')"
                        :instructions="__('When mode = partial, store this many bytes of the body.')"
                    >
                        <Input :model-value="String(config.logging.partial_bytes)" read-only />
                    </Field>

                    <Field inline
                        :label="__('Masked request headers')"
                        :instructions="__('Header names whose values are replaced with *** in stored logs.')"
                    >
                        <Textarea
                            :model-value="arrayToString(config.logging.mask_headers)"
                            :rows="3"
                            read-only
                        />
                    </Field>

                    <Field inline
                        :label="__('Masked payload keys')"
                        :instructions="__('Top-level JSON body keys whose values are replaced with *** in stored logs.')"
                    >
                        <Textarea
                            :model-value="arrayToString(config.logging.mask_payload_keys)"
                            :rows="3"
                            read-only
                        />
                    </Field>
                </Card>
                </Panel>

                <Panel :heading="__('Pruning')">
                    <Card>
                    <Field inline
                        :label="__('Prune deliveries after (days)')"
                        :instructions="__('Deliveries older than this are removed by the webhook-manager:prune command. 0 = never.')"
                    >
                        <Input :model-value="String(config.logging.deliveries_after_days)" read-only />
                    </Field>

                    <Field inline
                        :label="__('Prune logs after (days)')"
                        :instructions="__('Log records older than this are removed. 0 = never.')"
                    >
                        <Input :model-value="String(config.logging.logs_after_days)" read-only />
                    </Field>
                </Card>
                </Panel>

                <Panel :heading="__('Debug')">
                    <Card>
                    <Field inline
                        :label="__('Expose full response in dev')"
                        :instructions="__('When enabled, full response bodies are surfaced in the CP even in partial mode.')"
                    >
                        <Switch
                            :model-value="config.logging.expose_full_response_in_dev"
                            :disabled="true"
                        />
                    </Field>
                </Card>
                </Panel>
        </div>

        <!-- ── Raw config panel ─────────────────────────────────────── -->
        <Panel :heading="__('Raw configuration')" class="mt-6">
                    <Card>
            <p class="text-sm text-gray-600 dark:text-dark-150 mb-4">
                {{ __('Full resolved config tree — useful for debugging environment-variable overrides.') }}
            </p>
            <CodeEditor
                :model-value="rawConfig"
                mode="json"
                :read-only="true"
                :line-numbers="true"
                class="font-mono text-sm"
            />
        </Card>
                </Panel>

        <ConfirmationModal
            :open="showSwitch"
            :title="__('webhook-manager::messages.storage_switch_to', { driver: storage.target_label })"
            :body-text="__('webhook-manager::messages.storage_switch_hint', { driver: storage.target_label })"
            :button-text="__('webhook-manager::messages.storage_switch_to', { driver: storage.target_label })"
            @confirm="switchStorage"
            @update:open="showSwitch = $event"
        />

    </div>
</template>
