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

    'errors' => [
        'inbound_not_implemented' => 'Inbound endpoints are not yet implemented in this build.',
        'rule_engine_not_implemented' => 'Rule engine is not yet implemented in this build.',
        'invalid_template' => 'Template syntax is invalid.',
        'invalid_url' => 'Destination URL is invalid.',
        'unsupported_method' => 'HTTP method :method is not supported.',
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
