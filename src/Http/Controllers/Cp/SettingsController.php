<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Auth\Support\SecretMasker;
use Goldnead\WebhookManager\Http\Requests\UpdateSettingsRequest;
use Goldnead\WebhookManager\Storage\StorageDriverManager;
use Goldnead\WebhookManager\Storage\StorageMigrator;
use Goldnead\WebhookManager\Support\Settings;
use Goldnead\WebhookManager\WebhookManagerServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Statamic\Http\Controllers\CP\CpController;

class SettingsController extends CpController
{
    /**
     * Display the Settings page.
     *
     * The form is drawn from {@see Settings::groups()} — the same definition the
     * validation and the boot-time config override read — so a field cannot
     * exist on screen without a rule behind it, or the other way round. What
     * this controller still assembles by hand is only what is *not* a setting:
     * the storage driver (an action, not a value), and the deployment-owned keys
     * that are shown so an operator can see them without being invited to edit
     * a value the next deploy would take back.
     */
    public function index(Request $request, StorageDriverManager $driver, StorageMigrator $migrator, Settings $settings): Response
    {
        // One check, two uses: read access to this page is not widened past
        // the people who may change it, and the form reads `canEdit` from the
        // same answer rather than assuming that stays true.
        // Not a `$canEdit` flag handed to the page: there is no read-only view
        // of this screen. It prints the resolved config, so read access is the
        // same access as write access, and a page prop describing a state the
        // controller cannot reach is a branch nobody ever exercises.
        abort_unless($request->user()?->can('manage webhook settings'), 403);

        return Inertia::render('webhook-manager::Settings/Index', [
            'groups' => Settings::groups(),
            'values' => $this->current($settings),
            'updateUrl' => cp_route('webhook-manager.settings.update'),
            'environment' => $this->environmentPayload(),
            'rawConfig' => json_encode($this->redacted((array) config('webhook-manager')), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'configFilePath' => config_path('webhook-manager.php'),
            'storage' => $this->storagePayload($driver, $migrator),
        ]);
    }

    /**
     * Write the settings form.
     *
     * Redirects back rather than answering with JSON, which is what makes this
     * a Control-Panel mutation instead of an API call: the Inertia visit brings
     * the progress bar, the flash toast, the unsaved-changes guard and a
     * working back button, none of which an `axios.patch` has.
     *
     * The redirect is also what keeps the page honest. The screen has to end up
     * showing the values as they now stand, not the ones that were typed: an
     * integer arrives from a text input as a string and comes back an integer,
     * a list arrives with the blank line the textarea left behind and comes
     * back compacted, and a value equal to the shipped default is dropped and
     * comes back as whatever the config file says. The JSON version answered
     * with exactly that — but only for the form. `rawConfig`, the resolved
     * config tree printed in the diagnostics panel, kept showing the state from
     * before the save until somebody reloaded by hand. `back()` re-renders
     * {@see self::index()}, so every prop on the page is rebuilt at once.
     */
    public function update(UpdateSettingsRequest $request, Settings $settings): RedirectResponse
    {
        $settings->save($this->normalise($request->validated()['settings']));

        return back()->with('success', __('webhook-manager::settings.saved'));
    }

    /**
     * Switch the active storage driver from the Control Panel: migrate the
     * existing config to the target store, then persist the choice so it
     * takes effect without any .env/shell access.
     */
    public function switchStorage(Request $request, StorageDriverManager $driver, StorageMigrator $migrator)
    {
        abort_unless($request->user()?->can('manage webhook settings'), 403);

        // A bare `abort(422)` carries no error bag, so Inertia had nothing to
        // hand the page and the refusal arrived as a blank stop. Validating
        // instead produces `errors.driver`, which the settings page renders.
        $validated = $request->validate([
            'driver' => ['required', Rule::in(StorageMigrator::DRIVERS)],
        ]);

        $target = (string) $validated['driver'];

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
            'count' => array_sum($copied),
        ]));
    }

    /**
     * Coerce the form's values into the types the config expects.
     *
     * HTML form controls hand back strings; `config('webhook-manager.retry.max_attempts')`
     * is read into arithmetic and a `"3"` there is a bug waiting for a strict
     * comparison — including the one that decides whether a value is back at its
     * default and the row may go.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function normalise(array $values): array
    {
        $fields = Settings::fields();
        $out = [];

        foreach ($values as $key => $value) {
            $field = $fields[$key] ?? null;

            if ($field === null) {
                continue;
            }

            if (($field['nullable'] ?? false) && ($value === '' || $value === null)) {
                $out[$key] = null;

                continue;
            }

            $out[$key] = match ($field['type']) {
                'boolean' => (bool) $value,
                'integer' => (int) $value,
                'list' => $this->normaliseList($value, (string) ($field['items'] ?? 'string')),
                default => (string) $value,
            };
        }

        return $out;
    }

    /**
     * A list control is a textarea of lines, so blanks and stray whitespace are
     * normal input, not errors: a trailing newline must not become a masking
     * rule for the empty header name.
     *
     * @return array<int, string|int>
     */
    protected function normaliseList(mixed $value, string $items): array
    {
        $trimmed = array_filter(
            array_map(fn ($item) => trim((string) $item), (array) $value),
            fn (string $item) => $item !== '',
        );

        if ($items === 'integer') {
            $trimmed = array_map(fn (string $item) => (int) $item, $trimmed);
        }

        return array_values($trimmed);
    }

    /**
     * The current value of every editable setting, keyed by dotted path.
     *
     * Flat, not nested: the form indexes by the same path the definition uses,
     * so the screen never has to know how the config file happens to be shaped.
     *
     * @return array<string, mixed>
     */
    protected function current(Settings $settings): array
    {
        $values = [];

        foreach (array_keys(Settings::fields()) as $key) {
            $values[$key] = $settings->value($key);
        }

        return $values;
    }

    /**
     * Settings the deployment owns, shown but never editable.
     *
     * All of these resolve from `env()`. A database row that outranks an env
     * var is a setting that changes back on the next deploy without anyone
     * touching the screen, and `alerts.slack.webhook_url` is a credential on top
     * of that — reported here as configured-or-not rather than printed.
     *
     * @return array<int, array{label: string, value: string, env: string}>
     */
    protected function environmentPayload(): array
    {
        $queue = config('webhook-manager.queue', []);
        $alerts = config('webhook-manager.alerts', []);
        $breaker = config('webhook-manager.circuit_breaker', []);

        $yesNo = fn (mixed $value) => $value
            ? __('webhook-manager::settings.environment.on')
            : __('webhook-manager::settings.environment.off');

        return [
            [
                'label' => __('webhook-manager::settings.environment.queue_connection'),
                'value' => (string) ($queue['connection'] ?? __('webhook-manager::settings.environment.app_default')),
                'env' => 'WEBHOOK_MANAGER_QUEUE_CONNECTION',
            ],
            [
                'label' => __('webhook-manager::settings.environment.queue_name'),
                'value' => (string) ($queue['name'] ?? 'default'),
                'env' => 'WEBHOOK_MANAGER_QUEUE_NAME',
            ],
            [
                'label' => __('webhook-manager::settings.environment.circuit_breaker'),
                'value' => $yesNo($breaker['enabled'] ?? true).' · '.__('webhook-manager::settings.environment.threshold', [
                    'count' => (int) ($breaker['threshold'] ?? 10),
                ]),
                'env' => 'WEBHOOK_MANAGER_CIRCUIT_BREAKER / WEBHOOK_MANAGER_CIRCUIT_THRESHOLD',
            ],
            [
                'label' => __('webhook-manager::settings.environment.alerts'),
                'value' => $yesNo($alerts['enabled'] ?? true).' · '.__('webhook-manager::settings.environment.throttle', [
                    'count' => (int) ($alerts['throttle_minutes'] ?? 15),
                ]),
                'env' => 'WEBHOOK_MANAGER_ALERTS / WEBHOOK_MANAGER_ALERT_THROTTLE',
            ],
            [
                'label' => __('webhook-manager::settings.environment.alert_recipients'),
                'value' => implode(', ', (array) ($alerts['mail']['recipients'] ?? [])) ?: __('webhook-manager::settings.environment.none'),
                'env' => 'WEBHOOK_MANAGER_ALERT_EMAILS',
            ],
            [
                'label' => __('webhook-manager::settings.environment.alert_chat_webhook'),
                'value' => ($alerts['slack']['webhook_url'] ?? null)
                    ? __('webhook-manager::settings.environment.configured')
                    : __('webhook-manager::settings.environment.not_set'),
                'env' => 'WEBHOOK_MANAGER_ALERT_SLACK_URL',
            ],
            [
                // Not env-backed, but routing wiring: the prefix is read while
                // routes are registered and `route:cache` freezes it, so a value
                // changed here would print URLs that answer 404 until somebody
                // clears the cache.
                'label' => __('webhook-manager::settings.environment.inbound_prefix'),
                'value' => '/'.WebhookManagerServiceProvider::inboundRoutePrefix(),
                'env' => 'config/webhook-manager.php',
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    protected function storagePayload(StorageDriverManager $driver, StorageMigrator $migrator): array
    {
        $active = $driver->current();
        $other = $active === 'flat' ? 'eloquent' : 'flat';

        return [
            'driver' => $active,
            'driver_label' => $this->driverLabel($active),
            'source' => $driver->source(), // 'control_panel' | 'config'
            'flat_path' => (string) config('webhook-manager.storage.flat.path', base_path('content/webhooks')),
            'counts' => $migrator->counts($active),
            'target' => $other,
            'target_label' => $this->driverLabel($other),
            'switch_url' => cp_route('webhook-manager.settings.storage'),
        ];
    }

    /**
     * The resolved config tree with its secrets blanked.
     *
     * The page prints this so an operator can see what the installation
     * actually resolved to, deployment-owned values included. Printed
     * verbatim it also hands the browser the alert webhook URL — a credential
     * that grants posting rights to a chat channel — where it lands in
     * screen shares, screenshots and any front-end error report.
     *
     * Masked by key name rather than by path, because the config tree grows:
     * a key called `secret` added next year is covered without anybody
     * remembering to come back here. A masked value keeps its first and last
     * four characters, which is enough to tell two credentials apart and not
     * enough to use one.
     *
     * The secret flag is inherited downwards. `webhook_urls => [a, b]` and
     * `credentials => ['token' => …, 'note' => …]` are both things a host may
     * put in its own config, and masking only the leaf whose *own* key matched
     * would print every one of them.
     *
     * @param  array<string,mixed>  $config
     * @return array<string,mixed>
     */
    protected function redacted(array $config, bool $inherited = false): array
    {
        $out = [];

        foreach ($config as $key => $value) {
            $secret = $inherited || (is_string($key) && $this->isSecretKey($key));

            if (is_array($value)) {
                $out[$key] = $this->redacted($value, $secret);

                continue;
            }

            $out[$key] = $secret && $value !== null
                ? SecretMasker::mask((string) $value)
                : $value;
        }

        return $out;
    }

    /**
     * Whether a config key names something that must not be printed.
     *
     * Substring matching, deliberately generous: a false positive costs a
     * masked value on a diagnostics panel, a false negative costs a
     * credential. `webhook_url` is in here because the chat alert URL *is*
     * the credential — there is nothing else to authenticate with.
     */
    protected function isSecretKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (['secret', 'token', 'password', 'passwd', 'api_key', 'apikey', 'webhook_url', 'private_key', 'credential'] as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function driverLabel(string $driver): string
    {
        return $driver === 'flat'
            ? __('webhook-manager::messages.storage_flat')
            : __('webhook-manager::messages.storage_database');
    }
}
