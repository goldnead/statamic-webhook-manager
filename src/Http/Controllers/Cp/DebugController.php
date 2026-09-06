<?php

namespace Goldnead\WebhookManager\Http\Controllers\Cp;

use Goldnead\WebhookManager\Auth\Support\SecretMasker;
use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Goldnead\WebhookManager\Registries\VariableResolverRegistry;
use Goldnead\WebhookManager\Storage\StorageDriverManager;
use Goldnead\WebhookManager\Storage\StorageMigrator;
use Goldnead\WebhookManager\WebhookManagerServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Statamic\Http\Controllers\CP\CpController;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * The Debug screen: what this installation actually resolved to, and the two
 * levers that are not settings.
 *
 * **Three panels came here from the deleted settings screen** when the settings
 * themselves moved to the suite's shared one in `statamic-brand-context`. None
 * of them is a setting, so none of them belonged in the shared layer, and all
 * three are diagnostics — which is what this page already was ("inspect the
 * runtime state without needing to read PHP logs or config files"):
 *
 * - **Deployment-owned values** ({@see environmentPayload()}): queue, alerts,
 *   circuit breaker, inbound prefix. Resolved from `env()` or frozen by
 *   `route:cache`; shown so they can be checked, never offered so they can be
 *   half-changed by a database row the next deploy takes back.
 * - **The resolved config tree** ({@see redacted()}), secrets masked. The one
 *   answer to "what is this install really running", which no form can give
 *   because most of the config is not editable.
 * - **The storage driver** ({@see storagePayload()}, {@see switchStorage()}):
 *   a detection plus an action. Switching it has to *move* the stored webhook
 *   configuration first, which is why it was never a form field.
 */
class DebugController extends CpController
{
    /**
     * Render the Debug utility page.
     *
     * Passes all registered triggers and resolver namespaces to the Vue layer
     * so users can inspect the runtime state of the Webhook Manager without
     * needing to read PHP logs or config files.
     *
     * **Two abilities reach this page, and they see different halves of it.**
     * `use webhook debug tools` is what it always wanted. `manage webhook
     * settings` was added when the storage switch and the diagnostics panels
     * moved here: that ability used to reach them on the settings screen, and
     * narrowing it to debug-tool holders would have taken the CP's only way of
     * switching the storage driver away from the people who own it. The debug
     * *actions* are untouched by this — preview and simulate check
     * `use webhook debug tools` in their own controllers — so the URLs are
     * withheld from a settings-only user and the panels stay hidden.
     *
     * The previewUrl and simulateUrl are forwarded only when the corresponding
     * routes exist (they are defined in routes/cp.php under the actions group).
     * If simulateUrl is null the "Simulate Trigger" panel is hidden in Vue.
     */
    public function index(
        Request $request,
        TriggerRegistry $triggers,
        VariableResolverRegistry $resolvers,
        StorageDriverManager $driver,
        StorageMigrator $migrator,
    ) {
        $canDebug = (bool) $request->user()?->can('use webhook debug tools');
        $canManageSettings = (bool) $request->user()?->can('manage webhook settings');

        abort_unless($canDebug || $canManageSettings, 403);

        $triggersData = collect($triggers->all())->map(fn ($t) => [
            'handle' => $t->handle(),
            'label' => $t->label(),
            'source_type' => $t->sourceType(),
            'description' => method_exists($t, 'description') ? $t->description() : null,
        ])->values();

        $resolversData = collect($resolvers->all())->map(fn ($r) => [
            'namespace' => $r->namespace(),
        ])->values();

        // Both action routes must exist; the Vue layer guards on simulateUrl
        // being non-null before rendering the "Simulate Trigger" panel.
        $previewUrl = $canDebug ? cp_route('webhook-manager.actions.preview-template') : null;
        $simulateUrl = $canDebug ? $this->routeExistsOrNull('webhook-manager.actions.simulate-trigger') : null;

        return Inertia::render('webhook-manager::Debug/Index', [
            'triggers' => $triggersData,
            'resolvers' => $resolversData,
            'previewUrl' => $previewUrl,
            'simulateUrl' => $simulateUrl,
            'canManageSettings' => $canManageSettings,
            'environment' => $this->environmentPayload(),
            'rawConfig' => json_encode($this->redacted((array) config('webhook-manager')), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'configFilePath' => config_path('webhook-manager.php'),
            'settingsUrl' => cp_route('webhook-manager.settings'),
            'storage' => $canManageSettings ? $this->storagePayload($driver, $migrator) : null,
        ]);
    }

    /**
     * Switch the active storage driver from the Control Panel: migrate the
     * existing config to the target store, then persist the choice so it
     * takes effect without any .env/shell access.
     *
     * Moved here verbatim from the deleted SettingsController, permission and
     * all. It is not a setting: the value only becomes true after the data has
     * been moved, and a form field that saved it would strand the
     * configuration in the old store.
     */
    public function switchStorage(Request $request, StorageDriverManager $driver, StorageMigrator $migrator)
    {
        abort_unless($request->user()?->can('manage webhook settings'), 403);

        // A bare `abort(422)` carries no error bag, so Inertia had nothing to
        // hand the page and the refusal arrived as a blank stop. Validating
        // instead produces `errors.driver`, which the page renders.
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
            'switch_url' => cp_route('webhook-manager.debug.storage'),
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

    /**
     * Return the named CP route URL, or null if the route is not registered.
     *
     * Useful so we can conditionally expose action endpoints to the Vue layer
     * without throwing RouteNotFoundException when optional features are absent.
     */
    private function routeExistsOrNull(string $name): ?string
    {
        try {
            return cp_route($name);
        } catch (RouteNotFoundException) {
            return null;
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
