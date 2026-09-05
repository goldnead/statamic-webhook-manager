<?php

return [
    'created' => 'Webhook created.',
    'integration_created' => ':name integration created — review and save.',

    // Auth config validation (Outbound / Inbound edit screens)
    'auth_config_invalid_json' => 'The auth config must be a valid JSON object.',
    'auth_config_required' => 'This auth type needs credentials. Enter the auth config as JSON, otherwise the request would go out unauthenticated.',
    'auth_config_hmac_secret_required' => 'HMAC signing needs a "secret" key in the auth config.',

    // Delivery detail / replay
    'test_sent' => 'Test request sent.',
    'send_webhook' => 'Send webhook',
    'send_webhook_button' => '{1} Send webhook|[2,*] Send webhook',
    'send_webhook_missing' => 'The selected webhook no longer exists.',
    'send_webhook_done' => 'Fired :name for :count entries.',

    // Storage driver (Settings → Storage)
    'storage_heading' => 'Storage',
    'storage_sub' => 'Where your webhook configuration is stored. Delivery history always stays in the database.',
    'storage_database' => 'Database',
    'storage_flat' => 'Flat file (YAML)',
    'storage_active_driver' => 'Active driver',
    'storage_source_control_panel' => 'set in the Control Panel',
    'storage_source_config' => 'from config / env',
    'storage_flat_path_label' => 'YAML location',
    'storage_records' => 'Stored configuration',
    'storage_switch_to' => 'Switch to :driver',
    'storage_switch_hint' => 'Copies all webhook configuration to :driver (id-for-id) and makes it the active store. Delivery records are untouched.',
    'storage_switched' => 'Storage switched to :driver — :count record(s) migrated.',
    'storage_already_active' => ':driver storage is already active.',
    'storage_counts_line' => ':outbound outbound · :inbound inbound · :rules rules · :templates templates',

    // Shown where a stored handle has no registered label — a scheme or
    // handler removed by an upgrade, or a row written by a newer version.
    // Never the handle itself: `static_header` at a reader is the schema's
    // vocabulary and cannot be told apart from a real label.
    'unknown_option' => 'Unknown',

    // Insights / observability screen
    'insights_title' => 'Insights',
    'insights_subtitle' => 'Delivery volume, success rate, latency and failures across your outbound webhooks.',
    'insights_all_webhooks' => 'All webhooks',
    'insights_range_days' => '{1} Last :count day|[2,*] Last :count days',
    'insights_total_deliveries' => 'Deliveries',
    'insights_success_rate' => 'Success rate',
    'insights_failed' => 'Failed',
    'insights_p95_latency' => 'p95 latency',
    'insights_volume_heading' => 'Delivery volume',
    'insights_volume_sub' => 'Successful vs failed deliveries per day.',
    'insights_success_heading' => 'Success rate over time',
    'insights_success_sub' => 'Daily share of deliveries that succeeded.',
    'insights_latency_heading' => 'Latency',
    'insights_latency_sub' => 'Response time percentiles across recorded attempts.',
    'insights_errors_heading' => 'Error breakdown',
    'insights_errors_sub' => 'Failures grouped by classified error type.',
    'insights_top_failing_heading' => 'Top failing endpoints',
    'insights_top_failing_sub' => 'Outbound webhooks with the most failures in this window.',
    'insights_empty' => 'No deliveries recorded in this window yet.',
    'insights_no_failures' => 'No failures in this window.',
    'insights_no_latency' => 'No latency recorded yet.',
    'insights_view_deliveries' => 'View deliveries',

    'updated' => 'Webhook updated.',
    'deleted' => 'Webhook deleted.',
    'enabled' => 'Webhook enabled.',
    'disabled' => 'Webhook disabled.',
    'tested' => 'Test request fired.',
    'replayed' => 'Delivery replayed.',
    'pruned' => ':count records pruned.',

    'endpoint_created' => 'Endpoint created.',
    'endpoint_updated' => 'Endpoint updated.',
    'endpoint_deleted' => 'Endpoint deleted.',
    'endpoint_enabled' => 'Endpoint enabled.',
    'endpoint_disabled' => 'Endpoint disabled.',

    'rule_created' => 'Rule created.',
    'rule_updated' => 'Rule updated.',
    'rule_deleted' => 'Rule deleted.',
    'rule_enabled' => 'Rule enabled.',
    'rule_disabled' => 'Rule disabled.',
    'rule_test_succeeded' => 'Rule test succeeded.',
    'rule_test_failed' => 'Rule test failed.',

    'template_created' => 'Template created.',
    'template_updated' => 'Template updated.',
    'template_deleted' => 'Template deleted.',
    'template_deleted_with_detach' => 'Template deleted. :count outbound webhook(s) were detached and now use their inline payload again.',

    // Empty-state copy used by the index pages of the redesigned CP.
    // Mirrors the tone of Statamic core messages (form_configure_intro etc.).
    'outbound_empty_intro' => 'Outbound webhooks send notifications from your Statamic site to external services whenever a trigger event fires.',
    'outbound_create_description' => 'Configure an outbound webhook with a trigger, destination URL, payload template and authentication.',

    'inbound_empty_intro' => 'Inbound endpoints accept HTTP requests from external services and translate them into Statamic actions.',
    'inbound_create_description' => 'Define an inbound endpoint with a path, authentication scheme and a mapping to entries, actions or stored payloads.',

    'rules_empty_intro' => 'Rules apply conditional logic to webhook deliveries — match an event with conditions and run one or more actions.',
    'rules_create_description' => 'Build a rule with a trigger, optional conditions, and the actions to execute when it matches.',

    'templates_empty_intro' => 'Templates are reusable payload bodies and notification messages referenced by outbound webhooks and rules.',
    'templates_create_description' => 'Create a template with a handle, type and rendered body using token variables like {{ entry:title }}.',

    // Persistent help copy shown above the populated listings and edit screens.
    'rules_help' => 'Rules react to an incoming trigger (a Statamic event or an inbound webhook), evaluate optional conditions, and then run actions such as dispatching an outbound webhook. Use them to wire events to webhook deliveries without writing code.',
    'templates_help' => 'Templates define the reusable JSON or body payload that an outbound webhook sends. Reference template variables that get filled from the trigger payload at delivery time. Attach a template to an outbound webhook so multiple webhooks can share one payload shape.',
    'rules_edit_hint' => 'Pick a trigger, add optional conditions, then define the actions that run when it matches. This is how you connect an event to one or more webhook deliveries.',
    'templates_edit_hint' => 'Define a reusable payload body with template variables that get filled from the trigger payload at delivery time. Attach it to an outbound webhook so multiple webhooks can share one shape.',

    // The object a delivery was about (subject_type / subject_id).
    'subject' => 'Subject',
    'subject_type_placeholder' => 'Subject type',
    'subject_id_placeholder' => 'Subject ID',
    'subject_apply' => 'Filter',
    'subject_clear' => 'Clear filter',
    'subject_filter_active' => 'Deliveries for :type :id',
    'subject_deliveries_heading' => 'Webhook deliveries for this object',
    'subject_deliveries_empty' => 'No webhook deliveries recorded for this object.',
    'subject_deliveries_all' => 'All deliveries',

    'subject_types' => [
        'payment' => 'Payment',
        'offer' => 'Offer',
        'funnel' => 'Funnel',
        'contact' => 'Contact',
        'entry' => 'Entry',
        'user' => 'User',
        'asset' => 'Asset',
        'form_submission' => 'Form submission',
    ],

    'errors' => [
        'invalid_template' => 'Template syntax is invalid.',
        'invalid_url' => 'Destination URL is invalid.',
        'unsupported_method' => 'HTTP method :method is not supported.',
        'inbound_endpoint_not_found' => 'Endpoint not found or disabled.',
        'inbound_unauthorized' => 'Unauthorized.',
        'inbound_method_not_allowed' => 'Method not allowed.',
        'inbound_payload_too_large' => 'Payload too large.',
        'inbound_bad_request' => 'Bad request.',
        'inbound_replay_blocked' => 'Duplicate request blocked by replay protection.',
        'inbound_mapping_failed' => 'Mapping failed.',
        'rule_unknown_action' => 'Unknown action handler: :handle',
        'rule_invalid_conditions' => 'Invalid condition tree.',
    ],

    /*
     * Control Panel chrome: headings, column labels, field labels, row actions.
     *
     * These live under the addon's own namespace rather than in a
     * `resources/lang/de.json`, because JSON keys are global across every
     * installed addon and the collision is not hypothetical: six sibling
     * addons register `loadJsonTranslationsFrom`, and `statamic-marketing`
     * defines `"Delivery": "Versand"`. Until this file existed, the delivery
     * detail screen of THIS addon was titled "Versand #266" in German — a
     * marketing word for a webhook delivery, contributed by an addon that has
     * nothing to do with webhooks. A namespaced key cannot be redefined from
     * the outside.
     */
    'cp' => [
        // Overview
        'overview' => 'Overview',
        'stats_heading' => 'Key figures',
        'stats_sub' => 'Current state of this installation.',
        'stat_metric' => 'Metric',
        'stat_value' => 'Value',
        'stat_outbound_active' => 'Active outbound webhooks',
        'stat_inbound_active' => 'Active inbound endpoints',
        'stat_success_rate' => 'Success rate (24h)',
        'stat_failures' => 'Failed deliveries',
        'webhooks_heading' => 'Outbound webhooks',
        'webhooks_sub' => 'Every outbound webhook of this brand, in alphabetical order.',
        'webhooks_all' => 'All outbound webhooks',
        'webhooks_empty' => 'No outbound webhook configured yet.',
        'failures_heading' => 'Recent failures',
        'failures_sub' => 'The last eight failed deliveries — inspect or replay them.',
        'failures_all' => 'All deliveries',
        'get_started' => 'Get started with the Webhook Manager',
        'get_started_sub' => 'Send notifications to external services, accept incoming requests, and run automation rules — all from inside Statamic.',
        'create_outbound' => 'Create outbound webhook',
        'create_inbound' => 'Create inbound endpoint',
        'create_rule' => 'Add a rule',

        // Trigger and auth-scheme labels. They come out of the registries
        // (TriggerRegistry / AuthSchemeRegistry) and were the last English
        // strings on otherwise German screens: they render as badges in both
        // listings, on the delivery page and inside the editor's own tabs.
        'trigger_entry_saved' => 'Entry — saved',
        'trigger_entry_published' => 'Entry — published',
        'trigger_entry_unpublished' => 'Entry — unpublished',
        'trigger_entry_deleted' => 'Entry — deleted',
        'trigger_form_submitted' => 'Form — submitted',
        'trigger_user_saved' => 'User — saved',
        'trigger_asset_saved' => 'Asset — saved',
        'auth_none' => 'No authentication',
        'auth_static_header' => 'Static header secret',
        'auth_bearer' => 'Bearer token',
        'auth_basic' => 'Basic auth',
        'auth_hmac' => 'HMAC signature',
        'auth_ip_allowlist' => 'IP allowlist',

        // Shared column labels
        'col_name' => 'Name',
        'col_trigger' => 'Trigger',
        'col_method' => 'Method',
        'col_url' => 'URL',
        'col_status' => 'Status',
        'col_when' => 'When',
        'col_error' => 'Error',

        // Row actions
        'action_edit' => 'Edit',
        'action_test' => 'Test',
        'action_open_delivery' => 'Open delivery',
        'action_not_available_here' => 'That action is not available on this screen.',
        'action_selection_gone' => 'None of the selected records exist any more. Reload the page.',
        'action_wrong_type' => 'That action does not apply to the selected records.',
        'status_active' => 'Active',
        'status_disabled' => 'Disabled',

        // Bulk actions (native Statamic actions, shown in the "…" menu and
        // in the bulk bar once rows are checked).
        'bulk_enable' => 'Enable',
        'bulk_enable_button' => '{1} Enable webhook|[2,*] Enable :count webhooks',
        'bulk_enabled' => '{1} Webhook enabled.|[2,*] :count webhooks enabled.',
        'bulk_disable' => 'Disable',
        'bulk_disable_button' => '{1} Disable webhook|[2,*] Disable :count webhooks',
        'bulk_disabled' => '{1} Webhook disabled.|[2,*] :count webhooks disabled.',
        'bulk_delete' => 'Delete',
        'bulk_delete_button' => '{1} Delete webhook|[2,*] Delete :count webhooks',
        'bulk_delete_confirm' => '{1} This permanently removes the webhook configuration. Past deliveries are kept.|[2,*] This permanently removes :count webhook configurations. Past deliveries are kept.',
        'bulk_deleted' => '{1} Webhook deleted.|[2,*] :count webhooks deleted.',
        'bulk_replay' => 'Replay',
        'bulk_replay_button' => '{1} Replay delivery|[2,*] Replay :count deliveries',
        'bulk_replayed' => '{1} Delivery queued for replay.|[2,*] :count deliveries queued for replay.',

        // Delivery detail
        'delivery' => 'Delivery',
        'delivery_status' => [
            'pending' => 'Pending',
            'processing' => 'Processing',
            'success' => 'Succeeded',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
        ],
        'attempts' => 'Attempts',
        'duration' => 'Duration',
        'correlation_id' => 'Correlation ID',
        'request' => 'Request',
        'response' => 'Response',
        'headers' => 'Headers',
        'body' => 'Body',
        'status_code' => 'Status code',
        'timing_errors' => 'Timing and errors',
        'error_type' => 'Error type',
        'error_message' => 'Message',
        'next_retry' => 'Next retry',
        'payload_snapshot' => 'Payload snapshot',
        'payload_snapshot_sub' => 'The original event payload that triggered this delivery, stored at dispatch time.',
        'reproduce' => 'Reproduce',
        'reproduce_sub' => 'The exact request as a cURL command. Masked headers stay masked.',
        'copy' => 'Copy',
        'copied' => 'Copied',
        'replay' => 'Replay',
        'replay_ok' => 'Replayed successfully',
        'replay_failed' => 'Replay failed',

        // Outbound editor
        'tab_general' => 'General',
        'tab_trigger' => 'Trigger',
        'tab_request' => 'Request',
        'tab_auth' => 'Authentication',
        'tab_payload' => 'Payload',
        'tab_delivery' => 'Delivery',
        'tab_has_errors' => 'This tab contains errors',
        'edit_title_new' => 'Create outbound webhook',
        'edit_title_fallback' => 'Outbound webhook',
        'field_name' => 'Name',
        'field_name_hint' => 'Human-readable name shown across the Control Panel.',
        'field_handle' => 'Handle',
        'field_handle_hint' => 'Internal identifier. Lowercase, hyphens or underscores only.',
        'field_description' => 'Description',
        'field_status' => 'Status',
        'field_status_on' => 'Enabled',
        'field_status_off' => 'Disabled',
        'field_trigger_type' => 'Trigger type',
        'field_trigger_type_hint' => 'Internal event that fires this webhook.',
        'field_url' => 'URL',
        'field_url_hint' => 'The destination URL the webhook will hit on each delivery.',
        'field_method' => 'Method',
        'field_timeout' => 'Timeout (seconds)',
        'field_timeout_hint' => 'Hard cap per request, including TLS and redirects.',
        'field_follow_redirects' => 'Follow redirects',
        'field_follow_redirects_text' => 'Follow HTTP redirects',
        'field_auth_type' => 'Type',
        'field_auth_config' => 'Auth config (JSON)',
        'auth_secret_set_heading' => 'Secret already configured',
        'auth_secret_set_text' => 'Leave the field below empty to keep the existing encrypted secret.',
        'auth_hint_configured' => 'A secret is already configured. Leave blank to keep it. Paste new JSON to replace it — the value is encrypted at rest.',
        'auth_hint_new' => 'Stored encrypted. The format depends on the auth type — see the placeholder for an example.',
        'field_payload_type' => 'Type',
        'field_body_source' => 'Body source',
        'field_body_source_hint' => 'Write the body here, or pick a reusable template from the library. A library template wins when both are set.',
        'body_source_inline' => 'Inline template',
        'body_source_library' => 'Library template',
        'field_library_template' => 'Library template',
        'field_library_template_hint' => 'Pick a reusable outbound-body template. The renderer loads and renders it on each delivery.',
        'field_library_template_empty' => 'No outbound-body templates yet. Create one under Webhook Manager → Templates first.',
        'field_template' => 'Template',
        'field_template_hint' => 'Use tokens like {{ entry:title }} or {{ system:timestamp_iso }}.',
        'template_pick' => '— Pick a template —',
        'field_queue' => 'Queue',
        'field_queue_hint' => 'Recommended. Synchronous delivery only when latency is critical.',
        'field_queue_text' => 'Send asynchronously via the queue',
        'field_log_body' => 'Body logging',
        'field_log_body_hint' => 'Controls how much of the request and response bodies is written to the delivery log.',
        'payload_raw_json' => 'Raw JSON template',
        'payload_mapped' => 'Mapped object',
        'payload_form' => 'Form encoded',
        'log_full' => 'Full',
        'log_partial' => 'Partial',
        'log_none' => 'None',
        'delete_webhook' => 'Delete webhook',
        'delete_webhook_confirm' => 'This permanently removes the webhook configuration. Past deliveries are kept.',
        'delete' => 'Delete',
        'test_webhook' => 'Test webhook',
        'test_ok' => 'Test request succeeded',
        'test_failed' => 'Test request failed',
    ],

    'failure_types' => [
        'network' => 'Network error',
        'timeout' => 'Timeout',
        'auth' => 'Authentication error',
        'client' => 'Client error (4xx)',
        'server' => 'Server error (5xx)',
        'payload' => 'Payload error',
        'configuration' => 'Configuration error',
        'internal' => 'Internal app error',
    ],
];
