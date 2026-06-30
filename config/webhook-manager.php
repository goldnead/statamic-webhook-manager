<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Feature Toggles
    |--------------------------------------------------------------------------
    |
    | Enable or disable major modules of the addon. Each toggle hides the
    | module's CP screens, navigation entries and runtime wiring, so you can
    | run the addon as outbound-only (or any subset) without uninstalling.
    */
    'features' => [
        'outbound' => true,
        'inbound' => true,
        'rules' => true,
        'templates' => true,
        'debug_tools' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Outbound deliveries default to the queue ("queue-first") to keep the
    | CP responsive. Sync is only intended for testing.
    */
    'queue' => [
        'connection' => env('WEBHOOK_MANAGER_QUEUE_CONNECTION'),
        'name' => env('WEBHOOK_MANAGER_QUEUE_NAME', 'default'),
        'sync_in_console' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Defaults
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'strategy' => 'exponential', // none | linear | exponential
        'max_attempts' => 3,
        'base_delay_seconds' => 30,
        'max_delay_seconds' => 3600,
        'retry_on_status' => [408, 425, 429, 500, 502, 503, 504],
        'retry_on_network_errors' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery Logging
    |--------------------------------------------------------------------------
    |
    | Controls how request/response bodies are stored. `partial` keeps the
    | first N bytes, `none` stores nothing, `full` stores everything (use
    | with caution on large payloads).
    */
    'logging' => [
        'mode' => 'partial', // full | partial | none
        'partial_bytes' => 4096,
        'mask_headers' => [
            'authorization',
            'x-api-key',
            'x-auth-token',
            'cookie',
            'set-cookie',
        ],
        'mask_payload_keys' => [
            'password',
            'secret',
            'token',
            'api_key',
            'apikey',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pruning
    |--------------------------------------------------------------------------
    |
    | Runs via the `webhook-manager:prune` console command, ideally in the
    | scheduler. Set to 0 to disable.
    */
    'pruning' => [
        'deliveries_after_days' => 30,
        'logs_after_days' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Inbound
    |--------------------------------------------------------------------------
    */
    'inbound' => [
        'route_prefix' => '!/webhooks/inbound',
        'middleware' => ['web'],
        'max_payload_kb' => 512,
        'rate_limit_per_minute' => 60,
        'replay_protection_ttl_seconds' => 600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */
    'security' => [
        'hash_algorithms' => ['sha256', 'sha512'],
        'default_hash_algorithm' => 'sha256',
        'signature_header' => 'X-Webhook-Signature',
        'timestamp_header' => 'X-Webhook-Timestamp',
        'timestamp_tolerance_seconds' => 300,
        'mask_secrets_in_ui' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    */
    'http' => [
        'timeout_seconds' => 15,
        'connect_timeout_seconds' => 5,
        'follow_redirects' => true,
        'max_redirects' => 3,
        'user_agent' => 'Statamic-Webhook-Manager/1.0',
        'verify_ssl' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug
    |--------------------------------------------------------------------------
    */
    'debug' => [
        'expose_full_response_in_dev' => true,
    ],

];
