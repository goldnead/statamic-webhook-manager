<?php

return [
    'created' => 'Webhook created.',
    'integration_created' => ':name integration created — review and save.',
    'send_webhook' => 'Send webhook',
    'send_webhook_button' => '{1} Send webhook|[2,*] Send webhook',
    'send_webhook_missing' => 'The selected webhook no longer exists.',
    'send_webhook_done' => 'Fired :name for :count entries.',
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
