# Changelog

All notable changes to `goldnead/statamic-webhook-manager` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.0] — Inbound endpoints

The inbound module is now fully wired through. The public-facing
`InboundWebhookController` no longer returns 501 — incoming requests
flow through `auth → parse → replay → map → action → response` and
the action layer ships with seven built-in handlers covering the
common Statamic content sinks plus a generic event/audit-log path.

### Added

- **Inbound action layer.** New `InboundActionHandlerInterface` registry
  and seven built-in handlers under `Domain\InboundEndpoint\Handlers\`:
  `noop`, `create_entry`, `update_entry`, `upsert_entry`,
  `create_form_submission`, `dispatch_event`, `audit_log`.
- **Inbound request processor.** `Services\Inbound\InboundRequestProcessor`
  orchestrates the full pipeline (method allowlist → payload size →
  auth → content-type parse → replay protection → mapping →
  action dispatch → response builder) with structured `SystemLogger`
  entries on every failure.
- **Inbound action dispatcher.** `Services\Inbound\InboundActionDispatcher`
  resolves the configured `action_type` against the handler registry,
  catches handler exceptions, and returns a uniform
  `{ok, message, data}` shape.
- **Inbound domain actions:** `Create`, `Update`, `Delete`, `Toggle`,
  `Test` for endpoint CRUD; `Test` runs the mapping + action layer
  against a sample payload, bypassing HTTP auth and replay protection.
- **CP CRUD for inbound endpoints.** `Cp\InboundController` now
  exposes `index/create/store/edit/update/destroy/toggle`.
  `Cp\Actions\TestInboundController` powers the in-page test panel.
- **Vue pages.** `resources/js/pages/inbound/Index.vue` is now a real
  list view with search, status badges and a create button.
  `Edit.vue` ships as a sectioned form (Identity / Endpoint / Auth /
  Mapping / Action / Response) plus a Test panel that previews the
  mapped payload and the action result inline.
- **Public extension API.** `WebhookManager::registerInboundActionHandler()`
  for third parties to ship custom handlers.
- **Routes.** `routes/cp.php` extended with the inbound CRUD routes;
  `routes/actions.php` adds `actions.test-inbound`.
- **Repository.** `InboundEndpointRepository::paginate(int, ?string)`,
  `find()`, `findByUuid()` for the CP listing and lookups.
- **i18n.** `endpoint_*` messages for CRUD success notices and
  `inbound_*` error messages for pipeline failures.
- **Tests.**
  - `tests/Unit/Mappers/MappingEngineTest.php` — dot notation, array
    indices, defaults, required errors, transforms, type coercion.
  - `tests/Feature/InboundEndpointDispatchesActionTest.php` — full
    pipeline with `audit_log` action, plus 404/405/422 paths.
  - `tests/Feature/InboundEndpointRejectsInvalidSignatureTest.php` —
    HMAC valid/invalid/missing, plus static-header rejection.

### Changed

- `InboundWebhookController` is now thin — endpoint resolution stays in
  the controller, the rest delegates to `InboundRequestProcessor`.
- `InboundActionDispatcher` is no longer a stub; it dispatches via the
  new handler registry and uniformly logs failures.
- `WebhookManagerServiceProvider` binds the
  `InboundActionHandlerRegistry` singleton, registers built-in handlers
  on boot, and registers the `ReplayProtectionService` with the cache
  store + configurable TTL.

### Removed

- `messages.errors.inbound_not_implemented` translation key — the
  pipeline is implemented; specific error keys
  (`inbound_unauthorized`, `inbound_method_not_allowed`,
  `inbound_payload_too_large`, …) replace it.

### TODO: REVIEW

- `create_entry` / `update_entry` / `upsert_entry` handlers leave slug
  collision handling to Statamic (PRD §23). A v2 candidate is to
  classify the failure and surface a richer response.
- The mapping editor in the CP is JSON-first (PRD §43 explicitly
  allows this for v1). A visual mapping builder remains a v2
  candidate.
- Per-endpoint rate limiting is configurable in the schema
  (`rate_limit_config`) but not yet enforced — pending the rules
  iteration which shares the limiter.

## [0.2.0] — Statamic 6 / Inertia + Vue migration

### Changed (breaking)

- **Statamic 6 only.** `composer.json` now requires `statamic/cms: ^6.0` (previously `^5.0`). Statamic 6 ships an Inertia.js + Vue 3 SPA Control Panel; classical Blade CP views no longer fit nicely.
- **CP rendering moved from Blade to Vue.** All CP controllers return `Inertia::render('webhook-manager::Page/Name', $props)` instead of `view(...)`.
- **Build step required.** The addon now ships a Vite configuration and a built JS/CSS bundle under `resources/dist/`. End users do not need to build — the bundle is committed/shipped. Contributors run `npm install && npm run build` in the addon folder.

### Added

- `vite.config.js`, `package.json` and `resources/js/cp.js` entry point that registers each Vue page with Statamic's Inertia resolver via `Statamic.$inertia.register('webhook-manager::Page/Name', PageComponent)`.
- 11 Vue pages built with the Statamic `@ui` component library (`<ui-header>`, `<ui-panel>`, `<ui-listing>`, `<ui-field>`, `<ui-button>`, `<ui-badge>`, `<ui-confirmation-modal>`, …):
  - **Overview** dashboard with stats panels and recent failures table.
  - **Outbound** index (search + status badges) and edit screen (`useForm` from `@inertiajs/vue3`, sectioned panels, test button, delete confirm modal).
  - **Deliveries** index (status/trigger/error filters) and detail (request/response snapshots, replay, copy as cURL).
  - **Logs** index with level/type/correlation filters.
  - **Settings** read-only config view.
  - **Debug** page with trigger registry list and live template preview.
  - **Inbound**, **Rules**, **Templates** stub pages with `<ui-alert>` "coming next" notices.
- `$vite` property on `WebhookManagerServiceProvider` so the CP loads the addon's bundle.
- `inertiajs/inertia-laravel` runtime dependency.

### Removed

- `resources/views/cp/**` — all Blade CP views deleted.
- `resources/views/partials/**` — Blade `<x-…/>` components deleted.
- `resources/css/webhook-manager.css` — replaced by `resources/css/cp.css`.
- `loadViewsFrom()` call for the CP namespace (translations are still loaded).

### Unchanged

- All migrations, domain models, repositories, services, queue jobs, console commands, tests.
- Auth verifiers, template renderer, mapping engine, condition evaluator.
- Routes (`routes/cp.php`, `routes/actions.php`, `routes/inbound.php`).
- FormRequests and validation rules.
- Permissions / RBAC structure.
- Public extension API (`Goldnead\WebhookManager\Facades\WebhookManager`).

## [0.1.0] — initial Statamic 5 release

### Added

- Initial addon scaffold, service provider, config and CP navigation.
- Database migrations for outbound webhooks, inbound endpoints, rules, deliveries, logs, templates and secret audits.
- Domain models, repositories and contracts.
- Trigger registry with built-in triggers (entry saved/published/unpublished/deleted, form submitted, user saved, asset saved).
- Outbound webhook CRUD with form-request validation.
- Auth verifiers (none, static header, bearer, basic, HMAC SHA256) with a `SecretMasker` and signature generator.
- Token-based template renderer (`{{ namespace:key }}`) with variable resolver registry.
- Delivery engine: builder, HTTP client wrapper, success evaluator, failure classifier, retry planner and masking service.
- Queue jobs for outbound delivery, replay and pruning.
- Blade CP screens (replaced in 0.2.0).
- Console commands: prune, replay-failed, health, seed-examples.
- Permissions for granular RBAC.
- Unit tests for HMAC verification, failure classifier, retry planner, template renderer.
- Feature tests for outbound delivery, failure logging, replay flow and permission masking.

### Marked `TODO: REVIEW`

- Inbound endpoint controller returns `501 Not Implemented` until the full inbound flow ships.
- `RuleEngine::evaluate()` is a no-op — full rule evaluation pending.
- `MappingEngine` is a passthrough — JSON-based advanced config pending UI design.
- Template UI in CP shows a placeholder; the renderer itself is fully usable from outbound payloads.
