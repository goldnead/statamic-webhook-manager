# Statamic Webhook Manager

A central, CP-native integration layer for **[Statamic 6](https://statamic.com/)**. Manage **outbound webhooks**, **inbound endpoints**, **deliveries**, **retries**, **replays**, **rules** and **templates** — all from one place inside the Control Panel.

> **Status:** Stable on Statamic 6 (Laravel 11/12/13). Outbound webhooks, the delivery engine with retries & replay, inbound endpoints, the rule engine, payload templates and the full Vue + Inertia Control Panel are implemented and covered by the test suite.

---

## Features

- **Outbound webhooks** triggered by Statamic events (entry/form/user/asset) with conditional execution, payload templates, header & auth control, retry policies and queue-first delivery.
- **Integration presets** — guided "pick a destination → fill a URL" setup for Slack, Discord, Microsoft Teams, Zapier, Make, n8n and generic JSON, so you never hand-write a payload template.
- **Delivery snapshots** with full request/response bodies, status, error classification, attempts, retry schedule and replay support.
- **Replay** failed deliveries individually or in batches, optionally re-rendering against current data.
- **Failure alerting & circuit breaker** — email + Slack alerts (throttled per hook) when a delivery fails for good, and automatic disabling of a hook after too many consecutive failures.
- **Insights dashboard** — delivery volume, success-rate trend, latency percentiles (p50/p95/p99), error breakdown and top-failing endpoints, with day-range and per-webhook filters.
- **"Send webhook" entry action** — fire any enabled outbound webhook for selected entries straight from the native CP action toolbar.
- **Auth schemes**: none, bearer token, basic auth, custom header, HMAC SHA256 signature.
- **Token-based template renderer** (`{{ entry:title }}`, `{{ system:timestamp_iso }}`, …) with variable resolver registry.
- **Pluggable storage driver** — keep webhook config in the database, or as human-readable, git-versionable YAML under `content/webhooks/` (delivery history always stays in the database).
- **Permissions** for granular access to outbound config, sensitive payloads, replays, debug tools.
- **Native Statamic 6 CP** — built with Vue 3, Inertia.js and Statamic's `@ui` component library; fits seamlessly into the CP look & feel.

## Requirements

- PHP **8.2+**
- Statamic **6.0+**
- Laravel **11, 12 or 13**
- Node **18+** (only needed if you rebuild the CP bundle from source)
- A queue driver other than `sync` is strongly recommended.

## Installation

```bash
composer require goldnead/statamic-webhook-manager
php please vendor:publish --tag=webhook-manager-config
php artisan migrate
```

The Webhook Manager appears in the CP sidebar as **Webhooks**.

> **Note:** The pre-built CP bundle (`resources/dist/build/`) ships with the package. If you cloned the repo directly (e.g. via path repository) you'll need to build it yourself:
>
> ```bash
> npm install
> npm run build
> ```

## Configuration

See `config/webhook-manager.php` after publishing — feature toggles, retry policy, logging mode, masking rules, route prefixes, alerting/circuit-breaker, storage driver, etc.

### Storage driver

Webhook **configuration** (outbound webhooks, inbound endpoints, rules, templates) can be stored two ways. Delivery records and logs are runtime telemetry and always live in the database.

```php
// config/webhook-manager.php
'storage' => [
    'driver' => env('WEBHOOK_MANAGER_DRIVER', 'eloquent'), // 'eloquent' | 'flat'
    'flat' => [
        'path' => env('WEBHOOK_MANAGER_FLAT_PATH', base_path('content/webhooks')),
    ],
],
```

- **`eloquent`** (default) — config lives in database tables. Run `php artisan migrate`.
- **`flat`** — config lives as human-readable YAML under `content/webhooks/`, git-versionable alongside the rest of your site.

You can switch the active driver **in the Control Panel** (Settings → Storage) — it migrates the existing config to the target store and activates it, no `.env` access needed. A Control-Panel choice is persisted under `storage/` and takes precedence over the config/env default.

Or do it from the CLI (records are copied id-for-id either way):

```bash
php artisan webhook-manager:storage:migrate --from=eloquent --to=flat --dry-run
php artisan webhook-manager:storage:migrate --from=eloquent --to=flat
```

### Failure alerting

Set recipients (and an optional Slack webhook) so an admin is notified when a delivery fails after all retries; alerts are throttled per hook. A hook is auto-disabled after `circuit_breaker.threshold` consecutive terminal failures.

```dotenv
WEBHOOK_MANAGER_ALERT_EMAILS="ops@example.com,team@example.com"
```

## Concepts

- **Outbound webhook** — config for an HTTP request fired by an internal trigger.
- **Trigger** — internal event (e.g. `entry.published`, `form.submitted`).
- **Delivery** — one attempt to deliver a webhook, with full snapshot.
- **Rule** — `When → If → Then` flow with conditions and actions.
- **Inbound endpoint** — stable HTTPS URL receiving and validating external requests.

## Usage example

1. CP → Webhooks → Outbound → Create.
2. Pick trigger `entry.published`, scope to a collection.
3. Set destination URL, method and HMAC secret.
4. Use the JSON template editor:

```json
{
  "id": "{{ entry:id }}",
  "title": "{{ entry:title }}",
  "site": "{{ site:handle }}",
  "updated_at": "{{ system:timestamp_iso }}"
}
```

5. Save, publish a test entry, watch it appear under **Deliveries**.

## Extending

The addon is intentionally registry-driven. Register your own from any service provider:

```php
use Goldnead\WebhookManager\Facades\WebhookManager;

WebhookManager::registerTrigger(new MyCustomTrigger());
WebhookManager::registerCondition(new MyCustomCondition());
WebhookManager::registerAction(new MyCustomAction());
WebhookManager::registerAuthScheme(new MyCustomAuthScheme());
WebhookManager::registerVariableResolver(new MyCustomResolver());
WebhookManager::registerSuccessEvaluator(new MyCustomEvaluator());
```

Each registry has its own contract under `Goldnead\WebhookManager\Contracts`.

## Architecture

- **Controllers** return `Inertia::render('webhook-manager::Page/Name', $props)` — they never render Blade for the CP.
- **Vue pages** live under `resources/js/pages/` and are registered to Inertia in `resources/js/cp.js` via `Statamic.$inertia.register(...)`.
- **Service Provider** ships a `$vite` configuration so Statamic loads the addon's bundled JS/CSS in the CP.
- **Build** uses Vite + the `@statamic/cms/vite-plugin` to consume Statamic's `dist-package` (`@statamic/cms/ui`, `@statamic/cms/inertia`).
- **Domain layer** (controllers, models, services, jobs, queue) is pure Laravel — no Vue, no Inertia coupling. The same code path serves both async deliveries and the CP test button.

## Roadmap

Forward-looking design questions that may evolve in future releases:

1. Antlers/Tokens vs. a dedicated mini-template language.
2. Whether outbound hooks are modeled as specialised rules or kept separate.
3. How editable replay snapshots should be.
4. Whether inbound directly writes content or always goes through the action layer.
5. Final extensibility API surface.

## Console commands

- `php please webhook-manager:prune` — purge old deliveries/logs.
- `php please webhook-manager:replay-failed` — bulk replay failures from the last N hours.
- `php please webhook-manager:health` — show counts and recent failures.
- `php please webhook-manager:seed-examples` — install sample fixtures.
- `php please webhook-manager:storage:migrate --from=… --to=…` — move config between the `eloquent` and `flat` storage drivers.

## Testing

```bash
composer install
composer test          # or: vendor/bin/phpunit
```

Feature tests cover the outbound delivery flow, failure logging, replay,
inbound dispatch & signature verification, rule execution, template CRUD and
permission masking; unit tests cover the renderer, mapper, condition/rule
engines, retry planner and HMAC verifier.

### Local playground

Spin up a full Statamic 6 site with the addon wired in as a path repository
(SQLite, CP user, seeded sample records) so you can click through the Control
Panel:

```bash
./scripts/setup-playground.sh
cd playground && php artisan serve     # → http://127.0.0.1:8000/cp
# login: admin@example.com / password
```

### End-to-end smoke test

`./scripts/smoke-test.sh` installs a throwaway Statamic project, wires the
addon, then renders a payload template and delivers it to a local receiver
through the real `DeliveryEngine`, asserting the `Delivery` is recorded as a
success.

## License

MIT — see [LICENSE](LICENSE).
