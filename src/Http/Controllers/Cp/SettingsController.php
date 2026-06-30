<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Storage\StorageDriverManager;
use Goldnead\WebhookManager\Storage\StorageMigrator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Statamic\Http\Controllers\CP\CpController;

class SettingsController extends CpController
{
    /**
     * Display the Settings page.
     *
     * v1: always read-only — config lives in config/webhook-manager.php.
     *     Set $isEditable = true once a DB-settings-layer exists.
     */
    public function index(Request $request, StorageDriverManager $driver, StorageMigrator $migrator): Response
    {
        abort_unless(
            $request->user()?->can('manage webhook settings'),
            403
        );

        return Inertia::render('webhook-manager::Settings/Index', [
            'config'         => $this->extractConfig(),
            'rawConfig'      => json_encode(config('webhook-manager'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'configFilePath' => config_path('webhook-manager.php'),
            'isEditable'     => false, // v1: flip to true when DB-settings-layer lands
            'storage'        => $this->storagePayload($driver, $migrator),
        ]);
    }

    /**
     * Switch the active storage driver from the Control Panel: migrate the
     * existing config to the target store, then persist the choice so it
     * takes effect without any .env/shell access.
     */
    public function switchStorage(Request $request, StorageDriverManager $driver, StorageMigrator $migrator)
    {
        abort_unless($request->user()?->can('manage webhook settings'), 403);

        $target = (string) $request->input('driver');
        abort_unless(in_array($target, StorageMigrator::DRIVERS, true), 422);

        $current = $driver->current();
        if ($target === $current) {
            return back()->with('success', __('webhook-manager::messages.storage_already_active', [
                'driver' => $this->driverLabel($target),
            ]));
        }

        $copied = $migrator->migrate($current, $target);
        $driver->setDriver($target);

        return back()->with('success', __('webhook-manager::messages.storage_switched', [
            'driver' => $this->driverLabel($target),
            'count'  => array_sum($copied),
        ]));
    }

    /**
     * @return array<string,mixed>
     */
    protected function storagePayload(StorageDriverManager $driver, StorageMigrator $migrator): array
    {
        $active = $driver->current();
        $other = $active === 'flat' ? 'eloquent' : 'flat';

        return [
            'driver'      => $active,
            'driver_label' => $this->driverLabel($active),
            'source'      => $driver->source(), // 'control_panel' | 'config'
            'flat_path'   => (string) config('webhook-manager.storage.flat.path', base_path('content/webhooks')),
            'counts'      => $migrator->counts($active),
            'target'      => $other,
            'target_label' => $this->driverLabel($other),
            'switch_url'  => cp_route('webhook-manager.settings.storage'),
        ];
    }

    protected function driverLabel(string $driver): string
    {
        return $driver === 'flat'
            ? __('webhook-manager::messages.storage_flat')
            : __('webhook-manager::messages.storage_database');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Break the flat config() array into per-tab buckets so the Vue template
     * only needs simple dot-access — no deep nesting in the template itself.
     *
     * Key naming convention: {tab}_{section}_{key}
     */
    protected function extractConfig(): array
    {
        return [
            'general'     => $this->extractGeneral(),
            'defaults'    => $this->extractDefaults(),
            'reliability' => $this->extractReliability(),
            'security'    => $this->extractSecurity(),
            'logging'     => $this->extractLogging(),
        ];
    }

    protected function extractReliability(): array
    {
        $alerts = config('webhook-manager.alerts', []);
        $breaker = config('webhook-manager.circuit_breaker', []);

        return [
            'circuit_breaker_enabled'   => (bool) ($breaker['enabled'] ?? true),
            'circuit_breaker_threshold' => (int) ($breaker['threshold'] ?? 10),

            'alerts_enabled'          => (bool) ($alerts['enabled'] ?? true),
            'alerts_throttle_minutes' => (int) ($alerts['throttle_minutes'] ?? 15),
            'alerts_mail_enabled'     => (bool) ($alerts['mail']['enabled'] ?? true),
            'alerts_mail_recipients'  => implode(', ', (array) ($alerts['mail']['recipients'] ?? [])),
            'alerts_slack_configured' => ! empty($alerts['slack']['webhook_url'] ?? null),
        ];
    }

    protected function extractGeneral(): array
    {
        $features = config('webhook-manager.features', []);
        $queue    = config('webhook-manager.queue', []);

        return [
            // Feature flags
            'features_outbound'     => (bool) ($features['outbound']     ?? true),
            'features_inbound'      => (bool) ($features['inbound']      ?? true),
            'features_rules'        => (bool) ($features['rules']        ?? true),
            'features_templates'    => (bool) ($features['templates']    ?? true),
            'features_debug_tools'  => (bool) ($features['debug_tools']  ?? true),

            // Queue
            'queue_connection'       => $queue['connection']       ?? null,
            'queue_name'             => $queue['name']             ?? 'default',
            'queue_sync_in_console'  => (bool) ($queue['sync_in_console'] ?? false),
        ];
    }

    protected function extractDefaults(): array
    {
        $retry = config('webhook-manager.retry', []);
        $http  = config('webhook-manager.http',  []);

        return [
            // Retry
            'retry_strategy'              => $retry['strategy']              ?? 'exponential',
            'retry_max_attempts'          => (int)  ($retry['max_attempts']          ?? 3),
            'retry_base_delay_seconds'    => (int)  ($retry['base_delay_seconds']    ?? 30),
            'retry_max_delay_seconds'     => (int)  ($retry['max_delay_seconds']     ?? 3600),
            'retry_on_status'             => $retry['retry_on_status']             ?? [],
            'retry_on_network_errors'     => (bool) ($retry['retry_on_network_errors'] ?? true),

            // HTTP
            'http_timeout_seconds'        => (int)  ($http['timeout_seconds']        ?? 15),
            'http_connect_timeout_seconds'=> (int)  ($http['connect_timeout_seconds'] ?? 5),
            'http_follow_redirects'       => (bool) ($http['follow_redirects']       ?? true),
            'http_max_redirects'          => (int)  ($http['max_redirects']          ?? 3),
            'http_user_agent'             => $http['user_agent'] ?? 'Statamic-Webhook-Manager/1.0',
            'http_verify_ssl'             => (bool) ($http['verify_ssl']             ?? true),
        ];
    }

    protected function extractSecurity(): array
    {
        $inbound  = config('webhook-manager.inbound',  []);
        $security = config('webhook-manager.security', []);

        return [
            // Inbound route
            'inbound_route_prefix'                  => $inbound['route_prefix']             ?? '/webhooks/inbound',
            'inbound_max_payload_kb'                => (int) ($inbound['max_payload_kb']            ?? 512),
            'inbound_rate_limit_per_minute'         => (int) ($inbound['rate_limit_per_minute']     ?? 60),
            'inbound_replay_protection_ttl_seconds' => (int) ($inbound['replay_protection_ttl_seconds'] ?? 600),

            // HMAC / signature
            'hash_algorithms'              => $security['hash_algorithms']        ?? ['sha256', 'sha512'],
            'default_hash_algorithm'       => $security['default_hash_algorithm'] ?? 'sha256',
            'signature_header'             => $security['signature_header']       ?? 'X-Webhook-Signature',
            'timestamp_header'             => $security['timestamp_header']       ?? 'X-Webhook-Timestamp',
            'timestamp_tolerance_seconds'  => (int) ($security['timestamp_tolerance_seconds'] ?? 300),
            'mask_secrets_in_ui'           => (bool) ($security['mask_secrets_in_ui']         ?? true),
        ];
    }

    protected function extractLogging(): array
    {
        $logging = config('webhook-manager.logging', []);
        $pruning = config('webhook-manager.pruning', []);
        $debug   = config('webhook-manager.debug',   []);

        return [
            // Delivery logging
            'mode'              => $logging['mode']         ?? 'partial',
            'partial_bytes'     => (int) ($logging['partial_bytes'] ?? 4096),
            'mask_headers'      => $logging['mask_headers']       ?? [],
            'mask_payload_keys' => $logging['mask_payload_keys']  ?? [],

            // Pruning
            'deliveries_after_days' => (int) ($pruning['deliveries_after_days'] ?? 30),
            'logs_after_days'       => (int) ($pruning['logs_after_days']       ?? 60),

            // Debug
            'expose_full_response_in_dev' => (bool) ($debug['expose_full_response_in_dev'] ?? false),
        ];
    }
}
