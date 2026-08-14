<?php

/*
 * The editable settings screen.
 *
 * Every label and description the form shows comes from here, because the form
 * is generated from Support\Settings and that definition carries translation
 * keys rather than English strings. Field keys are the config path with the
 * dots flattened (`retry.max_attempts` → `retry_max_attempts`): a dot inside a
 * lang key is a path separator to the translator and would be looked up as
 * nested arrays that do not exist.
 */

return [

    'title' => 'Webhook Manager Settings',
    'intro' => 'These settings apply to the whole installation, not to one brand. Anything left untouched follows :path — changing a value here stores the difference, and setting it back to the shipped value removes it again.',
    'save' => 'Save',
    'saving' => 'Saving…',
    'saved' => 'Settings saved.',
    'save_failed' => 'The settings could not be saved.',
    'default_placeholder' => 'Default',

    'groups' => [
        'features' => [
            'title' => 'Modules',
            'description' => 'Which parts of the addon are active. A disabled module loses its Control Panel screens, its navigation entry and its runtime wiring — the data it already created stays where it is.',
        ],
        'retry' => [
            'title' => 'Retry defaults',
            'description' => 'What happens after a delivery fails. These apply to deliveries that start after the change; attempts already scheduled keep the plan they were given.',
        ],
        'http' => [
            'title' => 'HTTP defaults',
            'description' => 'How outbound requests are made. A webhook may override the timeout and the redirect behaviour for itself.',
        ],
        'inbound' => [
            'title' => 'Inbound limits',
            'description' => 'What an incoming request may cost before it is refused. The public URL prefix is not here: it is read while routes are registered and frozen by route caching.',
        ],
        'security' => [
            'title' => 'Signatures',
            'description' => 'How signed requests are recognised on both sides. Changing a header name means every sender configured against the old one has to be told.',
        ],
        'logging' => [
            'title' => 'Delivery logging',
            'description' => 'How much of each request and response is written down, and what is blanked out before it is.',
        ],
        'pruning' => [
            'title' => 'Retention',
            'description' => 'How long history is kept. Pruning runs from the webhook-manager:prune command, so a value here only takes effect where that command is scheduled.',
        ],
        'debug' => [
            'title' => 'Debug',
            'description' => 'Extra detail in the Control Panel.',
        ],
    ],

    'fields' => [

        'features_outbound' => [
            'label' => 'Outbound webhooks',
            'description' => 'The engine that sends requests out when something happens in Statamic.',
        ],
        'features_inbound' => [
            'label' => 'Inbound endpoints',
            'description' => 'The public receiver. Off means configured endpoints stop answering, and senders get a 404.',
        ],
        'features_rules' => [
            'label' => 'Rules engine',
            'description' => 'Conditional routing and actions on top of triggers.',
        ],
        'features_templates' => [
            'label' => 'Payload templates',
            'description' => 'The shared template library. Webhooks that already reference a template keep using it.',
        ],
        'features_debug_tools' => [
            'label' => 'Debug tools',
            'description' => 'The Debug screen with trigger simulation and payload inspection.',
        ],

        'retry_strategy' => [
            'label' => 'Strategy',
            'description' => 'How the delay grows between attempts.',
        ],
        'retry_max_attempts' => [
            'label' => 'Maximum attempts',
            'description' => 'Total attempts including the first one. Reaching it is what makes a delivery fail for good and triggers the failure alert.',
        ],
        'retry_base_delay_seconds' => [
            'label' => 'Base delay (seconds)',
            'description' => 'The wait before the second attempt, and the value the strategy grows from.',
        ],
        'retry_max_delay_seconds' => [
            'label' => 'Maximum delay (seconds)',
            'description' => 'Upper bound for the exponential strategy, so a long-broken endpoint is not retried a day later.',
        ],
        'retry_retry_on_status' => [
            'label' => 'Retry on status codes',
            'description' => 'One HTTP status code per line. Anything not listed is treated as a final answer and is not retried.',
        ],
        'retry_retry_on_network_errors' => [
            'label' => 'Retry on network errors',
            'description' => 'A timeout or a refused connection is retried like a listed status code.',
        ],

        'http_timeout_seconds' => [
            'label' => 'Timeout (seconds)',
            'description' => 'Hard cap per request, including TLS and redirects.',
        ],
        'http_connect_timeout_seconds' => [
            'label' => 'Connect timeout (seconds)',
            'description' => 'How long the connection alone may take. Counts towards the total timeout.',
        ],
        'http_follow_redirects' => [
            'label' => 'Follow redirects',
            'description' => 'Off means a 3xx is the answer, and whether that counts as success is up to the success evaluator.',
        ],
        'http_max_redirects' => [
            'label' => 'Maximum redirects',
            'description' => 'How many hops are followed before the delivery is given up on.',
        ],
        'http_user_agent' => [
            'label' => 'User agent',
            'description' => 'Sent with every outbound request. Some receivers filter on it.',
        ],
        'http_verify_ssl' => [
            'label' => 'Verify TLS certificates',
            'description' => 'Off sends payloads to a server whose identity is not checked. Only for a staging host with a self-signed certificate, and only knowingly.',
        ],

        'inbound_max_payload_kb' => [
            'label' => 'Maximum payload (KB)',
            'description' => 'Larger requests are refused with 413 before anything reads the body.',
        ],
        'inbound_rate_limit_per_minute' => [
            'label' => 'Rate limit (per minute)',
            'description' => 'Requests per minute per endpoint, checked before authentication. 0 disables throttling entirely.',
        ],
        'inbound_replay_protection_ttl_seconds' => [
            'label' => 'Replay protection window (seconds)',
            'description' => 'How long a signature or request id is remembered, so the same request cannot be sent twice. Longer costs cache, shorter lets an old request through again.',
        ],

        'security_default_hash_algorithm' => [
            'label' => 'Default hash algorithm',
            'description' => 'Preselected when a new webhook or endpoint is signed. Existing ones keep the algorithm they were saved with.',
        ],
        'security_signature_header' => [
            'label' => 'Signature header',
            'description' => 'The header that carries the HMAC signature, outbound and inbound.',
        ],
        'security_timestamp_header' => [
            'label' => 'Timestamp header',
            'description' => 'The header that carries the request timestamp used against replays.',
        ],
        'security_timestamp_tolerance_seconds' => [
            'label' => 'Timestamp tolerance (seconds)',
            'description' => 'How far the sender clock may drift before a signed request is refused.',
        ],
        'security_mask_secrets_in_ui' => [
            'label' => 'Mask secrets in the Control Panel',
            'description' => 'Shows stored credentials as *** instead of their value. It hides them on screen; it does not change what is stored.',
        ],

        'logging_mode' => [
            'label' => 'Body logging',
            'description' => 'How much of the request and response body is stored with each delivery.',
        ],
        'logging_partial_bytes' => [
            'label' => 'Partial size (bytes)',
            'description' => 'In partial mode, how many bytes of each body are kept.',
        ],
        'logging_mask_headers' => [
            'label' => 'Masked headers',
            'description' => 'One header name per line. Their values are replaced with *** before the delivery is written down.',
        ],
        'logging_mask_payload_keys' => [
            'label' => 'Masked payload keys',
            'description' => 'One key name per line. Emptying this list means payloads are stored exactly as they were sent.',
        ],

        'pruning_deliveries_after_days' => [
            'label' => 'Keep deliveries for (days)',
            'description' => 'Delivery records older than this are removed. 0 keeps them forever.',
        ],
        'pruning_logs_after_days' => [
            'label' => 'Keep logs for (days)',
            'description' => 'Log entries older than this are removed. 0 keeps them forever.',
        ],

        'debug_expose_full_response_in_dev' => [
            'label' => 'Show full responses in development',
            'description' => 'Surfaces the complete response body in the Control Panel even when only part of it was stored.',
        ],

    ],

    'options' => [
        'retry_strategy' => [
            'none' => 'No retry',
            'linear' => 'Linear',
            'exponential' => 'Exponential',
        ],
        'logging_mode' => [
            'full' => 'Full body',
            'partial' => 'First N bytes',
            'none' => 'No body',
        ],
    ],

    'environment' => [
        'heading' => 'Managed by the deployment',
        'description' => 'These come from environment variables or from route registration, so they are shown here but changed where they are set. A database value that outranked an env var would come back on the next deploy, and the chat webhook is a credential.',
        'queue_connection' => 'Queue connection',
        'queue_name' => 'Queue name',
        'circuit_breaker' => 'Circuit breaker',
        'alerts' => 'Failure alerts',
        'alert_recipients' => 'Alert recipients',
        'alert_chat_webhook' => 'Chat alert webhook',
        'inbound_prefix' => 'Inbound URL prefix',
        'threshold' => 'after :count consecutive failures',
        'throttle' => 'at most every :count min',
        'app_default' => '(application default)',
        'configured' => 'Configured',
        'not_set' => 'Not set',
        'none' => 'None',
        'on' => 'On',
        'off' => 'Off',
    ],

];
