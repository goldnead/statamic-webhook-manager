<?php

return [
    'created' => 'Webhook created.',
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
