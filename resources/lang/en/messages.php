<?php

return [
    'created' => 'Webhook created.',
    'integration_created' => ':name integration created — review and save.',

    // Auth config validation (Outbound / Inbound edit screens)
    'auth_config_invalid_json' => 'The auth config must be a valid JSON object.',
    'auth_config_required' => 'This auth type needs credentials. Enter the auth config as JSON, otherwise the request would go out unauthenticated.',
    'auth_config_hmac_secret_required' => 'HMAC signing needs a "secret" key in the auth config.',

    // Delivery detail / replay
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

    'template_created' => 'Template created.',
    'template_updated' => 'Template updated.',
    'template_deleted' => 'Template deleted.',
    'template_deleted_with_detach' => 'Template deleted. :count outbound webhook(s) were detached and now use their inline payload again.',

    // Empty-state copy used by the index pages of the redesigned CP.
    // Mirrors the tone of Statamic core messages (form_configure_intro etc.).
    'outbound_empty_intro' => 'Outbound webhooks send notifications from your Statamic site to external services whenever a trigger event fires.',
    'outbound_create_description' => 'Configure an outbound webhook with a trigger, destination URL, payload template and authentication.',

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

    /*
     * An `errors` group of thirteen keys stood here and was never read by one
     * line of code. It was an attempt to translate the strings
     * InboundRequestProcessor puts in `{"ok": false, "error": …}` —
     * "Unauthorized.", "Payload too large.", "Method not allowed." — which are
     * hard-coded there on purpose. Those go into the HTTP response an EXTERNAL
     * caller receives, and that caller is a machine on someone else's server:
     * it does not want the CP operator's language, and translating them would
     * make the endpoint answer differently depending on who last logged in.
     * The keys are gone rather than wired up; the group is not a to-do.
     */

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
        'field_enabled' => 'Enabled',
        'webhook' => 'Webhook',
        'field_status' => 'Status',
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

        /*
         * Page chrome and forms for every remaining screen.
         *
         * These were global __('…') keys until 05.09.2026. Global means any
         * installed addon may redefine them — statamic-marketing did exactly
         * that for `Delivery`, and the delivery detail page read "Versand #266"
         * in German. Namespaced here, one owner per key.
         */
        'app_name' => 'Webhook Manager',
        'page_outbound' => 'Outbound Webhooks',
        'page_inbound' => 'Inbound Endpoints',
        'page_inbound_short' => 'Inbound',
        'page_deliveries' => 'Deliveries',
        'page_rules' => 'Rules',
        'page_templates' => 'Templates',
        'page_logs' => 'Logs',
        'page_settings' => 'Settings',
        'page_debug' => 'Debug',
        'page_integrations' => 'Integrations',
        'page_add_integration' => 'Add integration',
        'integrations_add_heading' => 'Add an integration',
        'col_code' => 'Code',
        'col_attempts' => 'Attempts',
        'col_handle' => 'Handle',
        'col_label' => 'Label',
        'col_type' => 'Type',
        'col_order' => 'Order',
        'col_level' => 'Level',
        'col_message' => 'Message',
        'col_updated' => 'Updated',
        'col_path' => 'Path',
        'col_namespace' => 'Namespace',
        'col_source_type' => 'Source Type',
        'col_actions' => 'Actions',
        'col_conditions' => 'Conditions',
        'col_correlation_id' => 'Correlation ID',
        'col_auth' => 'Auth',
        'state_success' => 'Success',
        'state_failed' => 'Failed',
        'row_delete' => 'Delete',
        'row_duplicate' => 'Duplicate',
        'row_view' => 'View',
        'row_enable' => 'Enable',
        'row_disable' => 'Disable',
        'row_remove' => 'Remove',
        'btn_save' => 'Save',
        'btn_create' => 'Create',
        'btn_back' => 'Back',
        'btn_copied' => 'Copied!',
        'btn_copy_url' => 'Copy URL',
        'btn_copy_path' => 'Copy path',
        'btn_dismiss' => 'Dismiss',
        'btn_preview' => 'Preview',
        'btn_run' => 'Run',
        'btn_run_test' => 'Run test',
        'btn_running' => 'Running…',
        'btn_rendering' => 'Rendering…',
        'btn_set_up' => 'Set up',
        'btn_show_json' => 'Show JSON',
        'generic_error' => 'Something went wrong',
        'confirm_irreversible' => 'This action cannot be undone.',
        'back_to_webhooks' => 'Back to webhooks',
        'issues' => 'Issues',

        // Inbound endpoints
        'inbound_create_button' => 'Create Endpoint',
        'inbound_create_heading' => 'Create Inbound Endpoint',
        'inbound_create_title' => 'Create inbound endpoint',
        'inbound_fallback_title' => 'Inbound endpoint',
        'inbound_empty_heading' => 'No inbound endpoints yet',
        'inbound_empty_sub' => 'Receive and process incoming webhook payloads from external services.',
        'inbound_delete' => 'Delete endpoint',
        'inbound_delete_confirm_title' => 'Delete endpoint?',
        'inbound_path_hint' => 'The URL path segment for this endpoint.',
        'inbound_full_url' => 'Full URL:',
        'inbound_name_placeholder' => 'My Inbound Endpoint',
        'inbound_handle_placeholder' => 'my-inbound-endpoint',
        'inbound_path_placeholder' => 'my-endpoint',
        'inbound_auth_none_hint' => 'No authentication. Anyone can post to this endpoint.',
        'inbound_auth_set_hint' => 'A secret is already configured. Leave blank to keep it. Paste new JSON to replace — the value is encrypted at rest.',
        'inbound_auth_hint' => 'Stored encrypted. Format depends on the auth type — see the placeholder for an example.',
        'inbound_methods_label' => 'Allowed HTTP Methods',
        'inbound_methods_hint' => 'Select which HTTP methods this endpoint will accept.',
        'inbound_mapping_intro' => 'Define a JSON mapping to transform the incoming payload before it is passed to the action.',
        'inbound_mapping_docs' => 'View mapping documentation',
        'inbound_mapping_label' => 'Mapping Config (JSON)',
        'inbound_mapping_hint' => 'Map incoming fields to output fields. Leave empty to pass the payload through unchanged.',
        /*
         * Two registries, two groups — kept apart on purpose even though four
         * handles read the same: one is what an inbound endpoint does with a
         * payload, the other is what a rule executes. Both returned their
         * labels as hard-coded English and both reached the screen (the
         * "Action" column of the inbound listing, the select in the rule form).
         * Same case as the trigger and auth labels, one registry along — and on
         * the first pass the second one was missed precisely because it shares
         * its names with the first.
         */
        'inbound_actions' => [
            'audit_log' => 'Write audit log entry',
            'noop' => 'Acknowledge only (no side effects)',
            'create_entry' => 'Create entry',
            'create_form_submission' => 'Create form submission',
            'dispatch_event' => 'Dispatch internal event',
            'update_entry' => 'Update entry',
            'upsert_entry' => 'Upsert entry by key',
            'upsert_lead' => 'Create or update LeadHub lead',
        ],
        'rule_actions' => [
            'create_entry' => 'Create entry',
            'create_form_submission' => 'Create form submission',
            'dispatch_event' => 'Dispatch internal event',
            'send_email' => 'Send email notification',
            'send_outbound_webhook' => 'Send outbound webhook',
            'send_slack_webhook' => 'Send Slack/Discord webhook',
            'set_field_value' => 'Set entry field value',
            'update_entry' => 'Update entry',
            'write_log_note' => 'Write log note',
        ],

        'inbound_action_type' => 'Action Type',
        'inbound_action_config' => 'Action Config (JSON)',
        'inbound_action_config_hint' => 'Configuration for the selected action. Format depends on the action type.',
        'inbound_response_config' => 'Response Config (JSON)',
        'inbound_response_hint' => 'Customise the HTTP response returned to the caller. Leave empty for the default 200 OK.',
        'inbound_test_heading' => 'Send a test payload',
        'inbound_test_hint' => 'This payload will be processed through the mapping and action pipeline.',
        'inbound_test_save_first' => 'Save the endpoint first to enable testing.',
        'inbound_test_mapped' => 'Mapped Payload',
        'inbound_test_action_result' => 'Action Result',
        'inbound_test_errors' => 'Errors',

        // Tabs and fields shared by several forms
        'tab_authentication' => 'Authentication',
        'tab_allowed_methods' => 'Allowed Methods',
        'tab_mapping' => 'Mapping',
        'tab_action' => 'Action',
        'tab_response' => 'Response',
        'tab_test' => 'Test',
        'field_auth_config_json' => 'Auth Config (JSON)',
        'field_handle_hint_short' => 'Lowercase letters, numbers, underscores and hyphens only.',
        'sample_payload_json' => 'Sample Payload (JSON)',
        'test_ok_generic' => 'Test successful.',
        'test_failed_generic' => 'Test failed.',
        'test_request_failed' => 'Test request failed.',
        'test_request_failed_short' => 'Test request failed',
        'test_request_ok_short' => 'Test request succeeded',
        'outbound_create_heading' => 'Create Outbound Webhook',

        // Rules
        'rules_create_heading' => 'Create Rule',
        'rules_create_button' => 'Create rule',
        'rules_fallback_title' => 'Rule',
        'rules_delete' => 'Delete rule',
        'rules_delete_confirm' => 'This permanently removes the rule configuration.',
        'rules_trigger_hint' => 'The internal event that fires this rule.',
        'rules_trigger_config' => 'Trigger config (JSON)',
        'rules_trigger_config_hint' => 'Optional. Trigger-specific filter parameters — see the trigger\'s documentation for available keys.',
        'rules_conditions_hint' => 'Optional. AND/OR groups of leaf conditions. Leave empty to always match.',
        'rules_conditions_empty' => 'No conditions — rule fires for every matching trigger.',
        'rules_group_empty' => 'Empty group — add a condition or another nested group below.',
        'rules_condition' => 'Condition',
        'rules_group' => 'Group',
        'rules_remove_group' => 'Remove group',
        'rules_and' => 'AND',
        'rules_or' => 'OR',
        'rules_no_value_needed' => 'No value needed',
        'rules_json_mode' => 'Editing JSON directly. Switch back to builder to use the visual editor.',
        'rules_json_invalid' => 'Invalid JSON — fix it before switching back to the builder.',
        'rules_json_invalid_conditions' => 'Invalid JSON in conditions.',
        'rules_json_invalid_actions' => 'Invalid JSON in actions.',
        'rules_actions_must_be_array' => 'Actions must be a JSON array.',
        'rules_actions_label' => 'Actions (JSON array)',
        'rules_actions_hint' => 'Actions are an ordered array. Each object requires at minimum a handle key. Available handles are listed below.',
        'rules_actions_available' => 'Available action handles:',
        'rules_actions_order_hint' => 'Each action runs in order. Stop on failure can be configured in the Settings tab.',
        'rules_stop_on_failure' => 'Stop on first action failure',
        'rules_stop_on_failure_hint' => 'When enabled, if any action returns an error the remaining actions in this rule are skipped.',
        'rules_order_hint' => 'Lower numbers run first. Rules with equal order are sorted by name.',
        'rules_test_hint' => 'This payload is passed to the rule engine as if it were a real trigger event.',
        'rules_test_matched' => 'Rule matched — actions executed',
        'rules_test_not_matched' => 'Rule did not match or an error occurred',
        'rules_test_detail' => 'Result detail',
        'subject_collection' => 'Collection',
        'subject_term' => 'Term',
        'placeholder_value' => 'value',
        'placeholder_csv' => 'comma, separated, values',

        // Templates
        'templates_create_heading' => 'Create Template',
        'templates_delete' => 'Delete Template',
        'templates_delete_confirm' => 'Are you sure you want to delete this template? Outbound webhooks using it will have their body source detached.',
        'templates_fallback_title' => 'Template',
        'templates_body_hint' => 'Template body. Twig / Antlers syntax is supported for non-JSON types.',
        'templates_name_placeholder' => 'My template',
        'templates_handle_placeholder' => 'my_template',
        'templates_json_format' => 'JSON format',
        'templates_preview_hint' => 'Provide a JSON object that will be passed as data to the template renderer.',
        'templates_render_preview' => 'Render preview',
        'templates_rendered_output' => 'Rendered Output',
        'templates_preview_failed' => 'Preview failed.',
        'templates_kind_outbound' => 'Outbound request body',
        'templates_kind_inbound' => 'Inbound response body',
        'templates_kind_notification' => 'Notification body',
        'templates_variables' => 'Available variables',

        /*
         * The log.
         *
         * The type column was headed "Fehlerart" and translated against the
         * eight failure classes of a delivery — a vocabulary that never appears
         * in it. The screen therefore showed the raw handles
         * `inbound_received`, `delivery_failed`, `inbound_auth_failed`, with an
         * equally raw `info`/`warning` next to them. What stands here is the
         * vocabulary the column actually carries: the 22 event types the
         * SystemLogger writes.
         */
        'col_log_type' => 'Event',
        'log_levels' => [
            'debug' => 'Debug',
            'info' => 'Info',
            'warning' => 'Warning',
            'error' => 'Error',
        ],
        'log_types' => [
            'delivery_success' => 'Delivery succeeded',
            'delivery_failed' => 'Delivery failed',
            'replay_executed' => 'Delivery replayed',
            'rule_executed' => 'Rule executed',
            'rule_condition_exception' => 'Rule condition threw',
            'alert_mail_failed' => 'Alert mail not delivered',
            'alert_slack_failed' => 'Slack alert not delivered',
            'inbound_received' => 'Request accepted',
            'inbound_audit' => 'Request logged',
            'inbound_auth_failed' => 'Authentication refused',
            'inbound_signature_without_timestamp' => 'Signature without timestamp',
            'inbound_endpoint_not_found' => 'Endpoint not found',
            'inbound_method_not_allowed' => 'HTTP method not allowed',
            'inbound_rate_limited' => 'Rate limit hit',
            'inbound_payload_too_large' => 'Payload too large',
            'inbound_parse_failed' => 'Payload could not be read',
            'inbound_replay_blocked' => 'Replay blocked',
            'inbound_mapping_failed' => 'Mapping failed',
            'inbound_action_succeeded' => 'Action executed',
            'inbound_action_failed' => 'Action failed',
            'inbound_action_exception' => 'Action aborted with an exception',
            'inbound_action_handler_missing' => 'Action type not registered',
            'inbound_brand_defaulted' => 'No brand given, default used',
            'inbound_brand_not_found' => 'Brand not found',
            'circuit_breaker_tripped' => 'Circuit breaker tripped',
            'configuration_error_dangling_template' => 'Dangling template referenced',
        ],

        // Logs and deliveries (empty states)
        'logs_empty_heading' => 'No logs yet',
        'logs_empty_item' => 'Nothing logged so far',
        'logs_empty_sub' => 'Logs are written automatically when webhooks are dispatched or received. Check back once some activity has occurred.',
        'logs_docs' => 'Learn about logs',
        'deliveries_empty_heading' => 'No deliveries yet',
        'deliveries_empty_item' => 'Nothing dispatched so far',
        'deliveries_empty_sub' => 'Deliveries are recorded automatically when outbound webhooks are fired. Check back once some activity has occurred.',

        // Integrations
        'integrations_empty_heading' => 'No integrations available',
        'integrations_empty_sub' => 'No presets are registered. Create an outbound webhook by hand, or register a preset from a service provider.',
        'integrations_empty_item_sub' => 'Slack, Discord, Zapier and more — pre-configured in a couple of clicks.',
        'integrations_intro' => 'Each preset pre-fills the payload, headers and auth — you just provide a URL and choose a trigger.',
        'integrations_trigger_hint' => 'The Statamic event that fires this integration.',
        'integrations_pick_destination' => 'Pick a destination',
        // The preset descriptions used to be hard-coded English in the preset
        // classes. The names (Slack, Discord, n8n) stay as they are called —
        // only "Generic JSON endpoint" was a phrase rather than a name.
        'preset_generic_label' => 'Generic JSON endpoint',
        'preset_generic' => 'POST a JSON payload of your choice to any HTTP endpoint.',
        'preset_slack' => 'Post a message to a Slack channel via an Incoming Webhook.',
        'preset_discord' => 'Post a message to a Discord channel via a channel webhook.',
        'preset_teams' => 'Post a message to a Microsoft Teams channel via an Incoming Webhook.',
        'preset_zapier' => 'Send a structured JSON event to a Zapier "Catch Hook" trigger.',
        'preset_make' => 'Send a structured JSON event to a Make custom-webhook trigger.',
        'preset_n8n' => 'Send a structured JSON event to an n8n Webhook node.',
        // Labels and instructions for the fields an integration's setup form
        // shows. These were hard-coded English in the preset classes too.
        'preset_fields' => [
            'message_label' => 'Message',
            'message_hint' => 'Supports tokens like {{ entry:title }} and {{ system:trigger }}.',
            'payload_template_label' => 'Payload template',
            'payload_template_hint' => 'JSON body. Tokens like {{ entry:title }} are rendered per delivery.',
            'url_generic_label' => 'Destination URL',
            'url_generic_hint' => 'The endpoint that will receive the JSON POST.',
            'url_slack_label' => 'Slack Incoming Webhook URL',
            'url_slack_hint' => 'Create one at api.slack.com → Incoming Webhooks.',
            'url_discord_label' => 'Discord Webhook URL',
            'url_discord_hint' => 'Channel Settings → Integrations → Webhooks → Copy URL.',
            'url_teams_label' => 'Teams Incoming Webhook URL',
            'url_teams_hint' => 'Channel → Connectors → Incoming Webhook → Create.',
            'url_zapier_label' => 'Zapier Catch Hook URL',
            'url_zapier_hint' => 'Zap → Trigger → Webhooks by Zapier → Catch Hook → Copy URL.',
            'url_make_label' => 'Make Webhook URL',
            'url_make_hint' => 'Scenario → Webhooks → Custom webhook → Copy address.',
            'url_n8n_label' => 'n8n Webhook URL',
            'url_n8n_hint' => 'Add a Webhook node and copy its Production URL.',
        ],

        // Debug
        'debug_triggers_heading' => 'Trigger Inspector',
        'debug_triggers_sub' => 'All triggers registered with the Webhook Manager.',
        'debug_resolvers_heading' => 'Resolver Namespaces',
        'debug_resolvers_sub' => 'Registered payload resolvers and their template namespaces.',
        'debug_preview_heading' => 'Template Preview',
        'debug_preview_sub' => 'Render a Webhook Manager template against a sample payload to verify output.',
        'debug_template_label' => 'Template (JSON)',
        'debug_template_hint' => 'Enter a JSON template using Antlers or plain values.',
        'debug_sample_payload' => 'Sample Payload',
        'debug_sample_payload_hint' => 'JSON object that will be passed to the template renderer.',
        'debug_render_ok' => 'Rendered successfully.',
        'debug_simulate_heading' => 'Simulate Trigger',
        'debug_simulate_sub' => 'Fire a registered trigger with a sample payload to test outbound webhooks end-to-end.',
        'debug_simulate_hint' => 'JSON object that will be dispatched as the trigger payload.',
        'debug_simulated' => 'Trigger simulated.',
        // Two messages that came out of a template literal in hard-coded
        // English, two lines away from their translated twins.
        'invalid_json' => 'Invalid JSON: :error',
        'invalid_sample_json' => 'Invalid sample JSON: :error',
        'source_entry' => 'Entry',
        'source_form_submission' => 'Form Submission',
        'source_user' => 'User',
        'source_asset' => 'Asset',

        // Settings and insights
        'settings_raw_heading' => 'Raw configuration',
        'settings_raw_sub' => 'Full resolved config tree — useful for debugging environment-variable overrides.',
        'insights_max' => 'Max',
        'send_webhook_field_hint' => 'Which outbound webhook to fire for the selected entries.',

        // Notification on final failure
        'notify_intro' => 'A webhook delivery has failed after all retries.',
        'notify_subject' => 'Webhook delivery failed: :name',
        'notify_webhook' => 'Webhook: :name',
        'notify_url' => 'URL: :url',
        'notify_status' => 'Status: :status',
        'notify_attempts' => 'Attempts: :attempts',
        'notify_error' => 'Error: :error',
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
        // DeliveryStatsService groups every failure without a classified type
        // under this literal. Without an entry the insights panel printed the
        // raw translation key at the reader.
        'unknown' => 'Not classified',
    ],
];
