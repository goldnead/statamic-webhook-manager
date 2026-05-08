# CP-UI Redesign — Local QA Checklist

This checklist is the smoke-test plan for the `feature/cp-ui-redesign` branch.
The redesign rewrites all 14 CP pages on top of Statamic 6 native patterns
(`<Listing>`, `<Tabs>`, `<PublishForm>`, `<CodeEditor>`, etc.). Every
controller switched to a unified shape (initialColumns / row() /
indexColumns()) and the `<Listing>` component drives search, sort and
pagination on the server.

Run through the checklist on a fresh `npm run dev` build, with the addon
linked into a Statamic 6 dev site.

## Setup

- [ ] `composer require goldnead/statamic-webhook-manager:dev-feature/cp-ui-redesign`
- [ ] `php artisan vendor:publish --tag=webhook-manager-config` (if not yet)
- [ ] `php artisan migrate`
- [ ] `npm install && npm run build` in the host site
- [ ] `php artisan webhook-manager:seed-examples` (creates demo outbound, inbound, rule, template, deliveries)

## Cross-Cutting

- [ ] **Dark mode** — toggle via the user menu, every page should remain readable; no white blocks, no broken contrast on Tabs/Badges/Inputs/CodeEditor
- [ ] **Strict accessibility / increased contrast** — toggle in user preferences, borders should darken, focus rings should remain
- [ ] **Browser-tab title** is set on every page (e.g. `Outbound Webhooks · Webhook Manager · Statamic`)
- [ ] **`<Header>` icon** renders on every page (no missing-icon placeholders)
- [ ] **Statamic nav entry** shows the addon and stays highlighted while on any subpage
- [ ] **Permissions gating**: log in as a user without the relevant ability, the affected pages should 403 or hide actions
- [ ] **Command Palette** (`Cmd+K`) lists Create / Test / Delete actions for the current page

## Overview (`/cp/webhook-manager`)

- [ ] Stat cards render with icons, labels, values
- [ ] When 0 outbound + 0 inbound + 0 rules: shows EmptyStateMenu with three CTAs (only the ones the user can create are visible)
- [ ] Recent failures listing only shows on populated state, links to delivery detail
- [ ] Quick Actions buttons navigate to the right pages

## Outbound (`/cp/webhook-manager/outbound`)

### Index
- [ ] Empty state shows EmptyStateMenu + "Create Outbound Webhook" item (gated by `manage outbound webhooks`)
- [ ] Listing shows: name+handle, trigger badge, method badge, URL (truncated middle), status badge
- [ ] Search box debounces (no GET on every keystroke), filters server-side
- [ ] Status badge: green/Active vs gray/Disabled
- [ ] Method badge: POST=green, GET=blue, PUT/PATCH=amber, DELETE=red
- [ ] Row dropdown: Edit, Test, Toggle (Enable/Disable) — all gated
- [ ] Pagination controls work; per-page selector persists in user preferences
- [ ] Sortable columns (name, trigger, status) reflect on the URL when `pushQuery` triggers

### Edit / Create
- [ ] Tabs render: General / Trigger / Request / Authentication / Payload / Delivery
- [ ] Header shows StatusIndicator + Active/Disabled badge (Edit only)
- [ ] Test button (Edit only, when `can_test`): firing it shows `<Alert variant=success/error>` with HTTP status, duration, error
- [ ] Save button: shortcut `mod+s` works, loading state shows
- [ ] Validation: submit empty form, required fields error, the **first failing tab** auto-activates and shows a red `!` badge in the trigger
- [ ] **General**: Switch enabled/disabled, handle pattern, description textarea
- [ ] **Trigger**: Select with all registered triggers
- [ ] **Request**: URL, method (Select POST/GET/PUT/PATCH/DELETE), timeout (number 1-120), follow-redirects Switch
- [ ] **Authentication**: type Select, when not "none" the JSON CodeEditor appears; placeholder changes per auth type; if a secret is already configured, an Info-Alert appears and an empty submit must NOT overwrite the stored secret
- [ ] **Payload**: type Select, body source RadioGroup (inline/library), library option disabled when no templates exist; CodeEditor switches mode (json/text) based on payload_type
- [ ] **Delivery**: queue Switch, body logging Select
- [ ] Delete: ConfirmationModal appears, only on Edit, gated by `can_delete`

## Inbound (`/cp/webhook-manager/inbound`)

### Index
- [ ] Same listing pattern as Outbound; columns: name+handle, path (font-mono), auth badge, action badge, status
- [ ] Path is rendered as a clickable link or copy-button — verify the full webhook URL works (POST to `/{prefix}/{handle}` from outside, e.g. via curl)
- [ ] Auth badge colours: gray=none, blue=signature/static_header, green=hmac
- [ ] Row dropdown: Edit, Toggle, Delete
- [ ] Empty state with Create CTA gated by `manage inbound endpoints`

### Edit / Create
- [ ] 7 tabs: General / Auth / Methods / Mapping / Action / Response / Test
- [ ] **Methods**: CheckboxGroup with GET/POST/PUT/PATCH/DELETE (NOT custom buttons)
- [ ] **Mapping**: CodeEditor (json mode) for `mapping_config` — verify a malformed JSON shows a backend validation error and auto-tabs into Mapping
- [ ] **Action**: action_type Select; CodeEditor for action_config; conditional fields per action_type if any
- [ ] **Response**: CodeEditor for response_config
- [ ] **Test**: sample payload (CodeEditor json), Run button, structured result (mapped payload + action result)
- [ ] Saving with empty `mapping_config_json` does NOT overwrite the stored `mapping_config` (same convention as auth secrets)

## Rules (`/cp/webhook-manager/rules`)

### Index
- [ ] Listing columns: name, trigger badge, action_count badge (RED when 0), order_index, status
- [ ] Same dropdown actions as Outbound

### Edit / Create
- [ ] Tabs: General / Trigger / Conditions / Actions / Settings / Test
- [ ] **Conditions**: ConditionGroup component renders; adding/removing conditions/groups works; "Show JSON" Switch toggles between visual builder and read-only JSON view
- [ ] **Actions**: CodeEditor (json mode) — Info-Alert explains the structure
- [ ] When the form is submitted with an invalid trigger, the right tab auto-opens
- [ ] Test tab fires a sample payload through the rule engine

## Templates (`/cp/webhook-manager/templates`)

### Index
- [ ] Listing columns: name, handle, type badge, updated
- [ ] Type badge colours: outbound_body=blue, notification=amber, other=gray
- [ ] Empty state with Create CTA

### Edit / Create
- [ ] Tabs: General / Body / Preview
- [ ] **General**: name, handle (slug), type Select, description
- [ ] **Body**: CodeEditor — mode is `json` for outbound_body, `text` for others
- [ ] **Preview**: sample payload (CodeEditor json), source type Select, "Render" button → result rendered as CodeEditor read-only; issues show as Alert
- [ ] Available variables list shows below preview (or as CardPanel)
- [ ] Delete: ConfirmationModal; if the template is referenced by outbound webhooks, the success message reports the detach count

## Deliveries (`/cp/webhook-manager/deliveries`)

### Index
- [ ] Listing columns: status badge, trigger, URL (mono+ellipsis), method badge, response code, attempts, when
- [ ] Filters: status, trigger, error_type, webhook_id, from/to — change applies via Listing's AJAX refresh
- [ ] Row dropdown: View, Replay (only when `can_replay`)
- [ ] Replay action triggers a new delivery and refreshes the listing

### Show
- [ ] Header: Delivery #ID, status badge, Replay button (when applicable)
- [ ] Side-by-side Request and Response panels on lg+, stacked on smaller screens
- [ ] Request panel: method badge, URL (font-mono), headers (CodeEditor json read-only), body (CodeEditor with auto-detected mode based on Content-Type)
- [ ] Response panel: status code badge, duration, headers, body
- [ ] Timing & Errors panel only shows when there is an error or `attempts > 1` or `next_retry_at` is set
- [ ] Replay button posts to `actions.replay-delivery` and shows result
- [ ] cURL copy is available (button or pre-formatted block)

## Logs (`/cp/webhook-manager/logs`)

- [ ] Listing columns: level badge, message (truncated), correlation_id (mono+ellipsis), error_type badge, when
- [ ] Filters: level (error/warning/info/debug), error_type, correlation_id, date range
- [ ] Empty state shows a friendly "Nothing logged yet" message
- [ ] Search filters across `message` and `correlation_id`

## Settings (`/cp/webhook-manager/settings`)

- [ ] Read-only PublishForm-style page; banner "These settings are managed in `config/webhook-manager.php`" with Copy-path button
- [ ] 4 Tabs: General / Defaults / Security / Logging — every field disabled
- [ ] Raw config CodeEditor (json read-only) at the bottom
- [ ] Permission `manage webhook settings` is required (otherwise 403)

## Debug (`/cp/webhook-manager/debug`)

- [ ] Header with hammer icon
- [ ] Trigger Inspector listing: handle (mono), label, source_type badge
- [ ] Resolver Namespaces listing (only shown when resolvers are registered)
- [ ] Template Preview panel: template CodeEditor, source type Select, sample payload CodeEditor, Preview button → rendered output as read-only CodeEditor; issues as Alert
- [ ] Simulate Trigger panel only renders when `simulateUrl` is non-null (route registered)
- [ ] Permission `use webhook debug tools` is required

## Regressions to verify (existing functionality)

- [ ] Outbound delivery still fires on entry.published / form.submitted etc.
- [ ] Inbound endpoint still accepts and processes valid signed requests
- [ ] Rule still matches conditions and runs actions
- [ ] Replay of a failed delivery still works end-to-end
- [ ] Masked payloads still hide secrets for users without `view sensitive payloads`
- [ ] Existing console commands still work (prune, replay-failed, health, seed-examples)
- [ ] **All existing Domain-layer tests pass** (`vendor/bin/pest`) — UI changes do not touch the action/service layer

## Known Limitations

These are out of scope for this PR (tracked as v2 candidates):

- Bulk actions on listings — `<Listing>` exposes the bulk-action UI but the addon has no `actions` endpoints yet, so the bulk dropdown shows an empty state
- Visual mapping builder for Inbound (PRD §43) — JSON CodeEditor for now
- Per-action form generators in Rules — JSON-only for now
- Editable settings via UI — read-only in v1, DB-settings layer is v2
- Drag-handle reorder for Rules order_index — number input only
- error_type as filter on Deliveries / Logs UI is wired up but no UI dropdown yet (URL-based for now)
