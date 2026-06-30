# Statamic Marketplace Copy

Source material for the Statamic Marketplace listing.

---

## Title

**Webhook Manager for Statamic**

## Tagline / Short Description

A central, CP-native integration layer for outbound webhooks, inbound endpoints, deliveries, retries, rules and templates — all from one place inside the Control Panel.

## Long Description

Webhook Manager turns Statamic into a first-class integration hub. Instead of scattering `Http::post(...)` calls across event listeners and remembering to handle retries, signatures and logging yourself, you configure everything from the Control Panel: which events fire which requests, how payloads are shaped, how endpoints authenticate, and what happens when a delivery fails.

Every outbound request is recorded as a **delivery snapshot** — full request/response bodies, status, error classification, attempts and the retry schedule — so you can see exactly what was sent and replay it with one click. **Inbound endpoints** give external systems a stable, authenticated URL that maps incoming payloads onto Statamic actions (create/update entries, form submissions, events). **Rules** add a `When → If → Then` automation layer, and a **token-based template renderer** keeps payloads readable.

It is the missing integration layer between your Statamic site and the rest of your stack.

## Positioning Sentence

Stop hand-rolling webhook glue code. Webhook Manager gives Statamic a configurable, observable, retry-safe integration layer in the Control Panel.

## Key Features

- Outbound webhooks triggered by Statamic events (entry / form / user / asset)
- Conditional execution, payload templates, custom headers and auth control
- Auth schemes: none, bearer token, basic auth, custom header, HMAC SHA256 signature
- Queue-first delivery with configurable retry policy (linear / exponential / none)
- Delivery snapshots with full request/response, error classification and attempts
- Replay failed deliveries individually or in batches, optionally re-rendered against current data
- Inbound endpoints: authenticated URLs that validate, map and dispatch external requests
- Rule engine: `When → If → Then` flows with conditions and ordered actions
- Token-based template renderer (`{{ entry:title }}`, `{{ system:timestamp_iso }}`, …)
- Reusable payload templates library
- Granular CP permissions (config, sensitive payloads, replays, debug tools)
- Sensitive-payload masking and structured logging
- Console commands for pruning, bulk replay, health checks and example fixtures
- Native Statamic 6 CP — Vue 3, Inertia.js and Statamic's `@ui` components

## Who It's For

- Statamic agencies and freelancers integrating client sites with other systems
- Teams that need to push site events to Slack, Zapier, n8n, Make, CRMs or internal APIs
- Anyone who needs reliable, observable, retry-safe webhooks without writing the plumbing
- Sites that must receive and validate inbound webhooks from third parties

## Who It's *Not* For

- Projects that only ever need a single fire-and-forget `Http::post`
- Full ETL / data-pipeline workloads
- Real-time streaming (this is request/response webhooks, not a message bus)

## Categories

Integrations · Automation · Workflow · Developer Tools · Utility

## Requirements

- PHP 8.2+
- Statamic 6.0+ (Laravel 11/12/13)
- A queue driver other than `sync` recommended for production

## Suggested Pricing Tiers

| Tier | Price | Includes |
|---|---|---|
| **Webhook Manager Core** | $79–129 | Outbound + inbound webhooks, deliveries, retries, replays, rules, templates, full CP |
| **Pro** | +$50 | Advanced auth schemes, rate limiting, extended audit retention, priority support |
| **Business Bundle** | $149–199 | Webhook Manager + LeadHub |

## Installation (for the listing)

```bash
composer require goldnead/statamic-webhook-manager
php artisan migrate
```

The pre-built Control Panel bundle ships with the package; no end-user build step is required. The addon appears in the CP sidebar as **Webhooks**.
