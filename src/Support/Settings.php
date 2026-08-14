<?php

namespace Goldnead\WebhookManager\Support;

use Goldnead\WebhookManager\Domain\Settings\Models\WebhookSetting;
use Goldnead\WebhookManager\Storage\StorageMigrator;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The settings an operator may change from the Control Panel, and the one place
 * that knows what those are.
 *
 * Three readers share this definition — the boot-time override, the validation
 * on the way in, and the screen that draws the form — so a setting is added by
 * adding one entry to {@see groups()} and nothing else falls out of step. The
 * screen in particular is generated from here rather than from a hand-kept list
 * of labels, which is what the read-only version was: a second description of
 * `config/webhook-manager.php`, down to its own copy of every default
 * (`?? 30`, `?? 'exponential'`, `?? false`). Those copies had already drifted —
 * the screen's fallback for `debug.expose_full_response_in_dev` is `false`
 * where the config file ships `true`.
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
 *   `bootSchedule()` *before* `bootAddon()`, so the flag is read before
 *   {@see apply()} has put anything on the config. A control that takes effect
 *   only after the next deploy is worse than no control.
 * - `event_triggers`: closures and class-strings, i.e. code.
 * - `security.hash_algorithms`: which algorithms exist is a property of PHP and
 *   of the signing code, not an operator choice. A typo in that list becomes a
 *   selectable algorithm that fails at signing time, far away from this screen.
 *   The one thing that *is* a choice — which of them is the default — is here.
 * - The storage panel's driver, record counts and source: those are a detection
 *   plus an action, not a setting, and stay where they are.
 */
class Settings
{
    /** Cache key for the stored overrides. */
    public const CACHE_KEY = 'webhook-manager.settings.overrides';

    public function __construct(protected Application $app) {}

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
    public static function groups(): array
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

    /**
     * Every editable field, flattened.
     *
     * @return array<string, array<string, mixed>> key => field
     */
    public static function fields(): array
    {
        $fields = [];

        foreach (static::groups() as $group) {
            foreach ($group['fields'] as $field) {
                $fields[$field['key']] = $field;
            }
        }

        return $fields;
    }

    /**
     * The stored overrides, keyed by dotted path.
     *
     * Cached because this is read on every boot, including the boot of every
     * queue worker that processes a delivery. A missing table (installed but not
     * migrated) or an unreachable cache is not fatal: no overrides means the
     * config file, which is exactly the behaviour before this screen existed.
     *
     * @return array<string, mixed>
     */
    public function overrides(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn () => $this->read());
        } catch (\Throwable) {
            try {
                return $this->read();
            } catch (\Throwable) {
                return [];
            }
        }
    }

    /** @return array<string, mixed> */
    protected function read(): array
    {
        return WebhookSetting::query()
            ->pluck('value', 'key')
            ->all();
    }

    /**
     * Push the stored overrides onto the live config.
     *
     * Overriding the config rather than teaching every reader about this class
     * is the whole point: `config('webhook-manager.retry.max_attempts')` is read
     * from the delivery engine, the retry planner, the console commands and the
     * jobs, and a second source of truth next to it would be one missed call
     * site away from a setting that looks changed and is not.
     */
    public function apply(): void
    {
        // `config:cache` boots the app fully and then dumps the whole resolved
        // config to `bootstrap/cache/config.php`. Applying here during that
        // build would bake the overrides into the cached file, and a baked
        // override outlives the row it came from: deleting a setting would
        // then have no effect at all until somebody ran `config:clear`.
        //
        // It would also poison the baseline below. On the next boot
        // `mergeConfigFrom` is skipped (the config is cached), so
        // `config('webhook-manager')` *is* the baked file — the snapshot would
        // record the override as the packaged default, and a value reset to
        // the file's default would then be stored as a row instead of deleted,
        // which is precisely the property this class promises.
        //
        // Skipping is safe: the cached config keeps the file values, and every
        // process that reads it applies the overrides on its own boot.
        if (method_exists($this->app, 'runningConsoleCommand') && $this->app->runningConsoleCommand('config:cache')) {
            return;
        }

        // Snapshot what the config *files* say, before anything stored covers
        // it. This is what "back to default" means on this install: the package
        // config as the host published and edited it, not the copy inside the
        // package — a site that changed a default in its own
        // `config/webhook-manager.php` must be able to return to that value.
        $this->baseline ??= config('webhook-manager', []);

        $overrides = $this->overrides();

        // The common case is an install that never opened the screen, and this
        // runs on every boot — including every queue worker's. Nothing stored,
        // nothing to do, and in particular no reason to build the field
        // definition, which translates every label in it.
        if ($overrides === []) {
            return;
        }

        $fields = static::fields();

        foreach ($overrides as $key => $value) {
            // Only keys this class offers. A row left behind by an older release
            // must not be able to set an arbitrary config path — `storage.driver`
            // and the alert credentials are one string away otherwise.
            if (! isset($fields[$key])) {
                continue;
            }

            config()->set('webhook-manager.'.$key, $value);
        }
    }

    /**
     * The value on screen for one field: the override if there is one, else
     * whatever the config file says.
     */
    public function value(string $key): mixed
    {
        return config('webhook-manager.'.$key);
    }

    /**
     * Write the changed settings.
     *
     * A value equal to the packaged default is *deleted* rather than stored, so
     * "back to default" is reachable from the form and the table does not
     * accumulate rows that pin a value to what it already was.
     *
     * @param  array<string, mixed>  $values  key => value, keys from {@see fields()}
     */
    public function save(array $values): void
    {
        $fields = static::fields();

        // All of it or none of it. A failure halfway through leaves a settings
        // table that matches no coherent state, and `update()` answers with
        // that half-written state as though it were the truth.
        DB::transaction(function () use ($values, $fields): void {
            $this->write($values, $fields);
        });

        $this->forget();
        $this->apply();
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $fields
     */
    protected function write(array $values, array $fields): void
    {
        foreach ($values as $key => $value) {
            if (! isset($fields[$key])) {
                continue;
            }

            if ($value === $this->packagedDefault($key)) {
                WebhookSetting::query()->where('key', $key)->delete();

                // Put the file's value back on the live config by hand.
                // `apply()` below only writes the overrides that exist, so a
                // deleted one would leave the old value standing in this
                // process — the row is gone, the screen says "default", and
                // everything reading `config()` until the next boot still gets
                // the value that was just taken away.
                config()->set('webhook-manager.'.$key, $value);

                continue;
            }

            WebhookSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    /** Drop the cached overrides so the next read sees the table. */
    public function forget(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable) {
            // No cache store is a running-without-cache install, not a failure
            // to save: `read()` then hits the table on every call anyway.
        }
    }

    /**
     * The config as the files have it, taken before any override was applied.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $baseline = null;

    /**
     * What the config files say, ignoring any override already applied to the
     * live config.
     *
     * Never `config()`: by the time anything asks, {@see apply()} has already
     * overwritten the live value with the override, so comparing against it
     * would report every stored value as equal to the default and delete the
     * lot on the next save. The package file is the last resort, for a call
     * that happens before `apply()` ever ran.
     */
    protected function packagedDefault(string $key): mixed
    {
        if ($this->baseline === null) {
            $this->baseline = require __DIR__.'/../../config/webhook-manager.php';
        }

        return data_get($this->baseline, $key);
    }
}
