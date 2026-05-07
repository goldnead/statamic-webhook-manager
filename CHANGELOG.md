# Changelog

All notable changes to `goldnead/statamic-webhook-manager` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.5.0] — Templates CRUD + outbound library reuse

Templates move from "renderer is usable but UI is a placeholder" to a
fully editable library. Outbound webhooks can now reference a template
by handle, so the same body lives in one place and updates propagate
to every hook that uses it.

### Added

- **CRUD domain actions** for templates: `Create`, `Update`, `Delete`.
  `Delete` detaches outbound webhooks that reference the template by
  handle so removing a library entry never silently disables a hook —
  the success notice surfaces the detach count to the operator.
- **`TemplateRepository`** gains `paginate(int, ?string $search, ?string $type)`,
  `find()`, `findByUuid()` to mirror the Outbound / Inbound / Rule
  repository surfaces.
- **`Cp\TemplateController`** grows from listing-only to full CRUD
  (`index/create/store/edit/update/destroy`). Index supports search +
  type filter; edit screen lists the registered variable resolver
  namespaces inline so authors discover them without leaving the page.
- **Vue pages.**
  - `pages/templates/Index.vue` — real list with search, type filter,
    and per-row edit links.
  - `pages/templates/Edit.vue` (new) — sectioned form (Identity / Body)
    plus a Preview panel that posts the current body to
    `actions.preview-template` and renders the result alongside any
    validation issues.
- **Outbound library reuse.** New nullable column
  `payload_template_handle` on `webhook_outbounds`. When set, the
  `HttpRequestFactory` resolves the body from the referenced template
  instead of the inline `payload_template`. The body source is selected
  via a new "Inline template / Library template" radio in the
  Outbound edit panel.
- **Tests.**
  - `tests/Feature/TemplateCrudTest.php` — Create / Update / Delete
    actions, default-handle slugification, detach behaviour on delete.
  - `tests/Feature/OutboundUsesLibraryTemplateTest.php` — body source
    precedence (library → inline → JSON event), missing-template fallback
    to inline, template-edit propagation to subsequent renders.
- **i18n.** `template_*` notices for CRUD success messages plus a
  detach-count variant for the delete flow.
- **Routes.** `routes/cp.php` adds the templates CRUD routes (the
  `actions.preview-template` route already existed).

### Changed

- **`HttpRequestFactory`** now takes the `TemplateRepository` as a third
  constructor dependency and resolves the body in this order:
  `payload_template_handle` → `payload_template` → JSON-encoded
  TriggerEvent. The library handle wins when both are set so an operator
  can promote an inline body to a library entry without having to also
  clear the inline field on every hook.
- **`SaveOutboundWebhookRequest`** allows `payload_template_handle`
  (nullable, must exist in `webhook_templates.handle`) and skips inline-body
  validation when the hook delegates to a library template — that body
  is validated on the Template edit screen instead.

### TODO: REVIEW

- A dangling `payload_template_handle` (where the referenced template
  was removed by a path that bypasses `DeleteTemplateAction`) silently
  falls back to the inline body. This keeps deliveries alive but hides
  the misconfiguration; classify as a configuration failure once the
  centralised observer mentioned in PRD §54 lands.
- The Edit screen's preview always uses `source_type=entry`. Future
  iteration: surface the four supported source types (entry / form /
  user / asset) so a notification template author can preview against
  the right resolver.

## [0.4.0] — Rule engine

The rule engine moves from no-op stub to fully functional. Rules can
now compose triggers with `When → If → Then` semantics: a trigger
fires, the condition tree is evaluated, and a configurable list of
actions executes (with optional stop-on-failure).

### Added

- **Nine built-in rule actions** under `src/Actions/`, all implementing
  `ActionInterface`:
  - `send_outbound_webhook` — fire an existing outbound webhook by handle
  - `create_entry` / `update_entry` — Statamic entries via `Statamic\Facades\Entry`
  - `create_form_submission` — `Statamic\Facades\Form` submission
  - `dispatch_event` — generic Laravel event dispatch (FQCN or string)
  - `send_email` — `Mail::raw` notification
  - `send_slack_webhook` — `Http::post` to Slack/Discord-compatible webhook URLs
  - `set_field_value` — single-field entry update with literal or path-sourced value
  - `write_log_note` — structured `SystemLogger` entry
- **`RuleEngine`** is no longer a stub. Loads `RuleRepository::activeForTrigger`,
  evaluates each rule's condition tree via `ConditionEvaluator`, runs
  the action chain via `ActionExecutor` and aggregates per-rule results
  into `ExecutionResult`s with structured action breakdowns. The engine
  also exposes `evaluateOne()` so the CP "Test rule" path can run a
  single rule against a synthetic context.
- **`Domain\OutboundWebhook\Actions\DispatchOutboundWebhookAction`** — extracted
  the snapshot+queue/sync logic that was private to `TriggerDispatcher`
  so the new `SendOutboundWebhookAction` re-uses the same code path.
- **Five domain actions** for rule CRUD: `Create`, `Update`, `Delete`,
  `Toggle`, `Test`.
- **Repository.** `RuleRepository::paginate(int, ?string)`, `find()`,
  `findByUuid()` to mirror `OutboundWebhookRepository` / `InboundEndpointRepository`.
- **CP CRUD.** `Cp\RuleController` grows from listing-only to full
  CRUD (`index/create/store/edit/update/destroy/toggle`).
  `Cp\Actions\TestRuleController` powers the in-page Test panel.
- **Routes.** `routes/cp.php` adds the rules CRUD routes;
  `routes/actions.php` adds `actions.test-rule`.
- **i18n.** `rule_*` notices for CRUD success messages and
  `errors.rule_*` for execution failures.
- **Vue pages.** `pages/rules/Index.vue` is a real list view (search,
  status badges, action count, order index).
  `Edit.vue` ships as a sectioned form (Identity / Trigger /
  Conditions / Actions / Test) with a JSON editor for the condition
  tree and the action list, plus a Test panel that runs a single
  rule against a sample payload and shows the per-action outcome.
- **`ActionRegistry::registerDefaults()`** — built-in actions are
  resolved through the container so dependencies (repositories,
  `DispatchOutboundWebhookAction`, `SystemLogger`) are wired
  automatically.
- **Tests.**
  - `tests/Unit/Rules/ConditionEvaluatorTest.php` — leaf operators,
    AND/OR groups, nested groups, in/not_in, contains/exists/empty,
    numeric comparisons, regex, the `site/locale/trigger/replay`
    field shortcuts.
  - `tests/Unit/Rules/RuleEngineTest.php` — disabled rules, failing
    conditions, ordered action execution, unknown-handle failure,
    stop-on-failure short-circuit.
  - `tests/Feature/RuleExecutesMultipleActionsTest.php` — full
    `TriggerDispatcher → RuleEngine` path with the `write_log_note`
    handler. Asserts ordering, trigger filtering, stop-on-failure,
    and `order_index` ordering.

### Changed

- `Services\TriggerDispatcher` no longer holds the snapshot+dispatch
  logic itself — it delegates to `DispatchOutboundWebhookAction`.
  Rules now evaluate **before** direct outbound resolution, so
  rules can dispatch additional outbound webhooks via
  `send_outbound_webhook` if needed. Direct-attached hooks are
  unaffected (PRD §39 REVIEW: hooks remain a separate dispatch path
  rather than a special-case rule).
- `WebhookManagerServiceProvider::bootRegistries()` now calls
  `ActionRegistry::registerDefaults()`.

### Removed

- `messages.errors.rule_engine_not_implemented` translation key — the
  engine is implemented; specific error keys
  (`rule_unknown_action`, `rule_invalid_conditions`) replace it.

### TODO: REVIEW

- The condition / action editors are JSON-first (PRD §29 explicitly
  allows this). A visual condition builder and a per-action form
  generator remain v2 candidates.
- Rule actions that touch Statamic facades (`create_entry`,
  `update_entry`, `set_field_value`, `create_form_submission`)
  catch and surface throws but do not classify them. v2: feed into
  the same `FailureClassifier` the delivery engine uses.
- `SendEmailAction` ships text-only. Once the template module has a
  rendering API on the public surface, accept a template handle
  instead of pre-rendered body.

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
