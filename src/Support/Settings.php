<?php

namespace Goldnead\WebhookManager\Support;

use Goldnead\BrandContext\Contracts\ProvidesSettings;
use Goldnead\BrandContext\Settings\SettingsRegistry;
use Goldnead\WebhookManager\Http\Controllers\Cp\DebugController;
use Goldnead\WebhookManager\Storage\StorageMigrator;

/**
 * The settings an operator may change from the Control Panel, and the one place
 * that knows what those are.
 *
 * **Only the field list lives here now.** The table, the form, the validation,
 * the routes and the config override come from the suite's shared settings
 * layer in `statamic-brand-context`; this class is the one part of the old
 * mechanism that was legitimately the addon's own, and it is registered with
 * {@see SettingsRegistry} in the service
 * provider's boot. Everything else that used to sit next to it — a model on
 * `webhook_settings`, an `UpdateSettingsRequest`, a `SettingsController`, a Vue
 * page — is gone.
 *
 * Three readers still share this definition — the config override, the
 * validation on the way in, and the screen that draws the form — so a setting
 * is added by adding one entry to {@see settingsGroups()} and nothing else
 * falls out of step. The screen in particular is generated from here rather
 * than from a hand-kept list of labels, which is what the read-only version
 * was: a second description of `config/webhook-manager.php`, down to its own
 * copy of every default (`?? 30`, `?? 'exponential'`, `?? false`). Those copies
 * had already drifted — the screen's fallback for
 * `debug.expose_full_response_in_dev` is `false` where the config file ships
 * `true`.
 *
 * **Overrides, not a copy.** Only keys somebody actually changed are stored.
 * Everything else keeps following `config/webhook-manager.php`, so upgrading
 * the package still moves the defaults, and a site that never opens this screen
 * is indistinguishable from one running a release before the screen existed.
 *
 * **What is not here, and why.**
 *
 * - Anything resolved from `env()`: `queue.connection`, `queue.name`, the whole
 *   `alerts` and `circuit_breaker` blocks, `storage.driver`, `storage.flat.path`.
 *   These belong to the deployment. `alerts.slack.webhook_url` is a credential
 *   and has no business in a database backup; `alerts.mail.recipients` is not
 *   even a config value but the *result* of parsing one (a comma-separated env
 *   string), so a stored override would have a shape the file never has. The
 *   rest of the block is excluded with them rather than half of it, because a
 *   database row that silently outranks an env var is its own kind of trap.
 * - `storage.driver`: it decides where outbound webhooks, endpoints, rules and
 *   templates live, and switching it under a running install has to *move* them
 *   first. That is what the storage panel on this same screen does, through
 *   {@see StorageMigrator}. A second, silent way to flip the same switch would
 *   strand the config in the old store.
 * - `inbound.route_prefix`, `inbound.legacy_route_prefixes`, `inbound.middleware`:
 *   routing wiring, read while routes are registered and frozen by
 *   `php artisan route:cache`. An operator who changed the prefix here would get
 *   a Control Panel printing endpoint URLs that answer 404 until somebody clears
 *   the route cache — a failure with no visible cause. `middleware` is a list of
 *   class names on top of that, and the config file already warns what adding
 *   `web` to it does.
 * - `retry.schedule`: Statamic's `AddonServiceProvider::boot()` calls
 *   `bootSchedule()` *before* `bootAddon()`, so the flag is read before the
 *   shared layer has put anything on the config. A control that takes effect
 *   only after the next deploy is worse than no control.
 * - `event_triggers`: closures and class-strings, i.e. code.
 * - `security.hash_algorithms`: which algorithms exist is a property of PHP and
 *   of the signing code, not an operator choice. A typo in that list becomes a
 *   selectable algorithm that fails at signing time, far away from this screen.
 *   The one thing that *is* a choice — which of them is the default — is here.
 * - The storage panel's driver, record counts and source: those are a detection
 *   plus an action, not a setting. They moved to the Debug screen with the
 *   rest of the old page's non-settings furniture — see
 *   {@see DebugController}.
 *
 * **Two arguments the deleted readers carried**, restated here because the code
 * that made them is gone and the shared layer makes them in its own words:
 *
 * - `apply()` returned early during `php artisan config:cache`. An override
 *   baked into `bootstrap/cache/config.php` outlives the row it came from, and
 *   the next boot reads the baked file as the packaged default — so a value
 *   reset to its default is stored instead of deleted, and that key is stuck.
 * - The packaged default is what the config *files* say, snapshotted before
 *   anything stored covers it, so a host that edited its own published
 *   `config/webhook-manager.php` can return to *its* value rather than to the
 *   copy inside the package.
 */
class Settings implements ProvidesSettings
{
    /**
     * The namespace every stored row of this addon carries.
     *
     * `webhook-manager`, the addon's own handle: it is already the config root,
     * the lang namespace, the CP route prefix, the Inertia page prefix and the
     * `extra.statamic.slug` in composer.json. Anything else would be a second
     * name for this package, and this one is stamped on every row in
     * `brand_settings` — renaming it later orphans every override an
     * installation has made, so it is chosen to match what the addon already
     * calls itself everywhere else and never changed again.
     */
    public static function settingsNamespace(): string
    {
        return 'webhook-manager';
    }

    /**
     * The config root unset values keep following.
     *
     * `config/webhook-manager.php`, merged under the key `webhook-manager` by
     * `bootWebhookConfig()`. Read off the provider rather than assumed equal to
     * the namespace — they happen to match here, which is not a rule.
     */
    public static function settingsConfigPath(): string
    {
        return 'webhook-manager';
    }

    /**
     * The permission that gates this addon's section of the shared screen.
     *
     * Exactly the string the service provider registers and the nav item asks
     * for. It is assigned to real user groups on installed sites; a name
     * derived from the namespace (`manage webhook-manager settings`) would
     * match nothing and take the section away without saying why.
     */
    public static function settingsPermission(): string
    {
        return 'manage webhook settings';
    }

    /**
     * The editable settings, in the order and grouping the screen shows them.
     *
     * `key` is the path under `webhook-manager.`. `type` drives both the control
     * on screen and the validation rule. `items` narrows what a `list` may hold.
     * `nullable` means empty is a real value — no field currently needs it, but
     * the readers all honour it, so a nullable setting can be added without
     * touching three files.
     *
     * @return array<int, array{title: string, description: string, fields: array<int, array<string, mixed>>}>
     */
    public static function settingsGroups(): array
    {
        return [
            [
                'title' => __('webhook-manager::settings.groups.features.title'),
                'description' => __('webhook-manager::settings.groups.features.description'),
                'fields' => [
                    static::field('features.outbound', 'boolean'),
                    static::field('features.inbound', 'boolean'),
                    static::field('features.rules', 'boolean'),
                    static::field('features.templates', 'boolean'),
                    static::field('features.debug_tools', 'boolean'),
                ],
            ],
            [
                'title' => __('webhook-manager::settings.groups.retry.title'),
                'description' => __('webhook-manager::settings.groups.retry.description'),
                'fields' => [
                    static::field('retry.strategy', 'select', [
                        'options' => static::options('retry_strategy', ['none', 'linear', 'exponential']),
                    ]),
                    static::field('retry.max_attempts', 'integer', ['min' => 1]),
                    static::field('retry.base_delay_seconds', 'integer', ['min' => 1]),
                    static::field('retry.max_delay_seconds', 'integer', ['min' => 1]),
                    // Status codes, not names: the list is compared against a
                    // real response status, and `"429"` never equals `429`.
                    static::field('retry.retry_on_status', 'list', ['items' => 'integer']),
                    static::field('retry.retry_on_network_errors', 'boolean'),
                ],
            ],
            [
                'title' => __('webhook-manager::settings.groups.http.title'),
                'description' => __('webhook-manager::settings.groups.http.description'),
                'fields' => [
                    static::field('http.timeout_seconds', 'integer', ['min' => 1]),
                    static::field('http.connect_timeout_seconds', 'integer', ['min' => 1]),
                    static::field('http.follow_redirects', 'boolean'),
                    static::field('http.max_redirects', 'integer', ['min' => 0]),
                    static::field('http.user_agent', 'string'),
                    static::field('http.verify_ssl', 'boolean'),
                ],
            ],
            [
                'title' => __('webhook-manager::settings.groups.inbound.title'),
                'description' => __('webhook-manager::settings.groups.inbound.description'),
                'fields' => [
                    static::field('inbound.max_payload_kb', 'integer', ['min' => 1]),
                    // 0 is "no throttling" and has to stay reachable, so this is
                    // the one integer here whose floor is zero on purpose.
                    static::field('inbound.rate_limit_per_minute', 'integer', ['min' => 0]),
                    static::field('inbound.replay_protection_ttl_seconds', 'integer', ['min' => 1]),
                ],
            ],
            [
                'title' => __('webhook-manager::settings.groups.security.title'),
                'description' => __('webhook-manager::settings.groups.security.description'),
                'fields' => [
                    static::field('security.default_hash_algorithm', 'select', [
                        'options' => static::hashAlgorithmOptions(),
                    ]),
                    static::field('security.signature_header', 'string'),
                    static::field('security.timestamp_header', 'string'),
                    static::field('security.timestamp_tolerance_seconds', 'integer', ['min' => 0]),
                    static::field('security.mask_secrets_in_ui', 'boolean'),
                ],
            ],
            [
                'title' => __('webhook-manager::settings.groups.logging.title'),
                'description' => __('webhook-manager::settings.groups.logging.description'),
                'fields' => [
                    static::field('logging.mode', 'select', [
                        'options' => static::options('logging_mode', ['full', 'partial', 'none']),
                    ]),
                    static::field('logging.partial_bytes', 'integer', ['min' => 1]),
                    static::field('logging.mask_headers', 'list'),
                    static::field('logging.mask_payload_keys', 'list'),
                ],
            ],
            [
                'title' => __('webhook-manager::settings.groups.pruning.title'),
                'description' => __('webhook-manager::settings.groups.pruning.description'),
                'fields' => [
                    // 0 disables pruning, so both of these floor at zero.
                    static::field('pruning.deliveries_after_days', 'integer', ['min' => 0]),
                    static::field('pruning.logs_after_days', 'integer', ['min' => 0]),
                ],
            ],
            [
                'title' => __('webhook-manager::settings.groups.debug.title'),
                'description' => __('webhook-manager::settings.groups.debug.description'),
                'fields' => [
                    static::field('debug.expose_full_response_in_dev', 'boolean'),
                ],
            ],
        ];
    }

    /**
     * One field, with its label and description taken from the lang files.
     *
     * The translation key is the config path with the dots flattened, because a
     * dot inside a lang key is a path separator to the translator and
     * `settings.fields.retry.strategy.label` would be looked up as four nested
     * arrays that do not exist.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected static function field(string $key, string $type, array $extra = []): array
    {
        $handle = str_replace('.', '_', $key);

        return array_merge([
            'key' => $key,
            'type' => $type,
            'label' => __("webhook-manager::settings.fields.{$handle}.label"),
            'description' => __("webhook-manager::settings.fields.{$handle}.description"),
            'nullable' => false,
        ], $extra);
    }

    /**
     * Options for a `select`, labelled from the lang files.
     *
     * @param  array<int, string>  $values
     * @return array<int, array{value: string, label: string}>
     */
    protected static function options(string $set, array $values): array
    {
        return array_map(fn (string $value) => [
            'value' => $value,
            'label' => __("webhook-manager::settings.options.{$set}.{$value}"),
        ], $values);
    }

    /**
     * The algorithms offered as the signing default.
     *
     * Taken from `security.hash_algorithms`, which is the list the signature
     * code actually accepts — and unioned with whatever is configured *now*, so
     * an install whose default was left out of that list can still save this
     * form. Without the union the current value would fail its own `in:` rule
     * and every other field on the page would be unsaveable with it.
     *
     * @return array<int, array{value: string, label: string}>
     */
    protected static function hashAlgorithmOptions(): array
    {
        $algorithms = array_values(array_unique(array_filter(array_merge(
            array_map('strval', (array) config('webhook-manager.security.hash_algorithms', [])),
            [(string) config('webhook-manager.security.default_hash_algorithm', 'sha256')],
        ))));

        return array_map(fn (string $value) => [
            'value' => $value,
            'label' => $value,
        ], $algorithms);
    }
}
