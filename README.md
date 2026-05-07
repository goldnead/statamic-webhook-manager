# Statamic Webhook Manager

A central, CP-native integration layer for [Statamic 5](https://statamic.com/). Manage **outbound webhooks**, **inbound endpoints**, **deliveries**, **retries**, **replays**, **rules** and **templates** — all from one place inside the Control Panel.

> **Status:** First pass — the **outbound module + delivery engine + CP grounding** are functionally implemented. Inbound, rules and templates ship as architectural stubs marked `TODO: REVIEW` and will be filled in iteratively.

---

## Features

- **Outbound webhooks** triggered by Statamic events (entry/form/user/asset) with conditional execution, payload templates, header & auth control, retry policies and queue-first delivery.
- **Delivery snapshots** with full request/response bodies, status, error classification, attempts, retry schedule and replay support.
- **Replay** failed deliveries individually or in batches, optionally re-rendering against current data.
- **Auth schemes**: none, bearer token, basic auth, custom header, HMAC SHA256 signature.
- **Token-based template renderer** (`{{ entry:title }}`, `{{ system:timestamp_iso }}`, …) with variable resolver registry.
- **Permissions** for granular access to outbound config, sensitive payloads, replays, debug tools.
- **Inbound endpoints**, **rules**, **template management** in CP — scaffolded as stubs in v0.1.0.

## Requirements

- PHP **8.2+**
- Statamic **5.x**
- A queue driver other than `sync` is strongly recommended.

## Installation

```bash
composer require goldnead/statamic-webhook-manager
php please vendor:publish --tag=webhook-manager-config
php artisan migrate
```

The Webhook Manager appears in the CP sidebar as **Webhooks**.

## Configuration

See `config/webhook-manager.php` after publishing — feature toggles, retry policy, logging mode, masking rules, route prefixes, etc.

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

The addon is intentionally registry-driven. Register your own:

```php
use Goldnead\WebhookManager\Facades\WebhookManager;

WebhookManager::registerTrigger(...);
WebhookManager::registerCondition(...);
WebhookManager::registerAction(...);
WebhookManager::registerAuthScheme(...);
WebhookManager::registerVariableResolver(...);
WebhookManager::registerSuccessEvaluator(...);
```

Each registry has its own contract under `Goldnead\WebhookManager\Contracts`.

## Open architecture decisions

These are deliberately marked `TODO: REVIEW` in code and will be revisited:

1. CP UI technology — Blade vs. Vue components.
2. Antlers/Tokens vs. a dedicated mini-template language.
3. Whether outbound hooks are modeled as specialised rules or kept separate.
4. How editable replay snapshots should be.
5. Whether inbound directly writes content or always goes through the action layer.
6. Final extensibility API surface.

## Console commands

- `php please webhook-manager:prune` — purge old deliveries/logs.
- `php please webhook-manager:replay-failed` — bulk replay failures from the last N hours.
- `php please webhook-manager:health` — show counts and recent failures.
- `php please webhook-manager:seed-examples` — install sample fixtures.

## Testing

```bash
composer install
vendor/bin/phpunit
```

Feature tests cover the outbound delivery flow, failure logging, replay and permission masking.

## License

MIT — see [LICENSE](LICENSE).
