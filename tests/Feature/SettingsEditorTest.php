<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\BrandContext\Models\BrandSetting;
use Goldnead\BrandContext\Settings\SettingsManager;
use Goldnead\BrandContext\Settings\SettingsRegistry;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Services\RetryPlanner;
use Goldnead\WebhookManager\Support\Settings;
use Goldnead\WebhookManager\Tests\CpTestCase;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

/**
 * The settings this addon offers, saved through the suite's shared screen.
 *
 * The addon's own screen is gone: no `webhook_settings` model, no
 * `UpdateSettingsRequest`, no `SettingsController`, no Vue page. What is left
 * is {@see Settings}, the field list, registered with
 * {@see SettingsRegistry}. Everything asserted here therefore crosses a package
 * boundary on purpose — the point is not that `statamic-brand-context` works
 * (its own suite covers that) but that **this addon's** fields reach the
 * screen, survive the round trip with their types intact, and end up on the
 * config where the delivery engine and the retry planner read them.
 */
class SettingsEditorTest extends CpTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->superUser());
    }

    /**
     * The form always submits every field, so the rules are `present` and a
     * partial payload is a rejection rather than a silent partial write. Tests
     * that care about one key say so, and this fills in the rest from config.
     *
     * Sent as an Inertia visit, because that is what the screen sends. The
     * endpoint answers a successful write with `back()`, so a saved settings
     * form is a 302 and a rejected one is a 302 back with an error bag — not
     * a 200 with a JSON body and not a 422.
     *
     * `namespace` rides along: the shared screen saves one addon's section at a
     * time, so a validation failure in one package cannot block saving another.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function patchSettings(array $overrides, ?Authenticatable $as = null): TestResponse
    {
        $settings = [];

        foreach ($this->fields() as $key => $field) {
            $value = config('webhook-manager.'.$key);

            // A `list` control on the shared screen is one textarea of lines,
            // so what actually arrives at the server is an array of strings —
            // and the shared validation insists on that (`settings.<key>.*` is
            // `string`). `retry.retry_on_status` holds integers in the config
            // file, so a payload built straight from `config()` would be
            // rejected where a real browser's is not, and every test here would
            // fail for the wrong reason. Mirrored rather than worked around:
            // the consequence of the strings surviving into storage is asserted
            // in test_a_saved_status_list_still_triggers_a_retry() below.
            if (($field['type'] ?? null) === 'list') {
                $value = array_map('strval', (array) $value);
            }

            $settings[$key] = $value;
        }

        $request = $as ? $this->actingAs($as) : $this;

        return $request
            ->withHeaders($this->inertiaHeaders())
            ->patch(cp_route('brand-context.settings.update'), [
                'namespace' => Settings::settingsNamespace(),
                'settings' => array_replace($settings, $overrides),
            ]);
    }

    /** This addon's section of the shared screen, on a fresh render. */
    protected function section(): array
    {
        $sections = $this->withHeaders($this->inertiaHeaders())
            ->get(cp_route('brand-context.settings.index'))
            ->assertOk()
            ->json('props.sections');

        foreach ($sections as $section) {
            if ($section['namespace'] === Settings::settingsNamespace()) {
                return $section;
            }
        }

        $this->fail('The shared settings screen has no section for '.Settings::settingsNamespace().'.');
    }

    /** @return array<string, array<string, mixed>> */
    protected function fields(): array
    {
        return app(SettingsRegistry::class)->fields(Settings::settingsNamespace());
    }

    /** How many stored rows this addon has for one key. */
    protected function rowCount(string $key): int
    {
        return BrandSetting::query()
            ->where('namespace', Settings::settingsNamespace())
            ->where('key', $key)
            ->count();
    }

    public function test_the_old_settings_url_leads_to_the_shared_screen(): void
    {
        // `/cp/webhook-manager/settings` is in bookmarks, in the docs and in
        // the sidebar. A 404 there tells nobody where the settings went, and
        // the nav item still points at it on purpose so that exactly one place
        // in this package knows the new address.
        $this->get(cp_route('webhook-manager.settings'))
            ->assertRedirect(cp_route('brand-context.settings.index'));
    }

    public function test_the_addon_registers_itself_with_the_shared_registry(): void
    {
        // Three strings that are frozen once a site has saved anything: the
        // namespace is stamped on every stored row, the config path decides
        // which config tree the overrides land on, and the permission is
        // assigned to real user groups. A rename of any of them is silent —
        // the screen simply stops showing this addon, or writes somewhere else.
        $registry = app(SettingsRegistry::class);

        $this->assertTrue($registry->has('webhook-manager'));
        $this->assertSame(Settings::class, $registry->provider('webhook-manager'));
        $this->assertSame('webhook-manager', $registry->configPath('webhook-manager'));
        $this->assertSame('manage webhook settings', $registry->permission('webhook-manager'));

        // The permission is the one the service provider actually registers,
        // not one derived from the namespace.
        $this->assertContains('manage webhook settings', $this->registeredAbilities());
    }

    public function test_it_stores_a_changed_setting_and_applies_it_to_the_config(): void
    {
        $this->patchSettings(['retry.max_attempts' => 5])->assertRedirect();

        $row = BrandSetting::query()
            ->where('namespace', 'webhook-manager')
            ->where('key', 'retry.max_attempts')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(5, $row->value);
        // Brand-scoped now, which the addon's own table never was.
        $this->assertSame(app('brand-context')->currentId(), $row->brand_id);

        $this->assertSame(5, config('webhook-manager.retry.max_attempts'));
    }

    public function test_it_coerces_a_number_typed_into_a_text_field_to_an_integer(): void
    {
        // HTML controls hand back strings, and the retry values are read into
        // arithmetic and into the comparison that decides whether a row may be
        // deleted. A `"5"` survives until the first strict comparison and then
        // fails somewhere else entirely.
        $this->patchSettings(['retry.max_attempts' => '5'])->assertRedirect();

        $this->assertSame(5, config('webhook-manager.retry.max_attempts'));
    }

    public function test_it_deletes_the_override_when_a_value_goes_back_to_the_default(): void
    {
        $this->patchSettings(['retry.max_attempts' => 5])->assertRedirect();
        $this->assertSame(1, $this->rowCount('retry.max_attempts'));

        // Not "stores 3" — stores nothing. A row pinning a value to what it
        // already was would freeze that default across package upgrades.
        $this->patchSettings(['retry.max_attempts' => 3])->assertRedirect();

        $this->assertSame(0, $this->rowCount('retry.max_attempts'));

        // And the running application has to agree in the same breath.
        // `apply()` only writes the overrides that exist, so a deleted one
        // would leave the old value standing until the next boot: the row
        // gone, the screen saying "default", and every reader still getting 5.
        $this->assertSame(3, config('webhook-manager.retry.max_attempts'));
    }

    public function test_the_page_it_redirects_to_shows_the_settings_as_they_now_stand(): void
    {
        // Keyed by the dotted path, flat — the same shape the form indexes by,
        // so the screen never has to know how the config file is nested. Read
        // off the re-rendered page rather than out of a JSON body: the point of
        // redirecting back is that the whole page is rebuilt.
        $this->patchSettings([
            'retry.max_attempts' => '5',
            'logging.mask_headers' => ['authorization', ' cookie ', ''],
        ])->assertRedirect();

        $values = $this->section()['values'];

        $this->assertSame(5, $values['retry.max_attempts']);
        $this->assertSame(['authorization', 'cookie'], $values['logging.mask_headers']);
    }

    public function test_it_says_so_when_the_settings_were_saved(): void
    {
        // The confirmation is core's flash toast, which only appears if the
        // controller actually flashes one. It now names the addon, because the
        // screen carries every addon's section at once.
        $this->patchSettings(['retry.max_attempts' => 5])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_a_rejected_field_comes_back_in_the_error_bag(): void
    {
        // An Inertia visit carries validation failures in the session error
        // bag, keyed the same way the form indexes its controls. A 422 with a
        // JSON body would arrive as an unhandled rejection instead.
        $this->patchSettings(['retry.max_attempts' => 0])
            ->assertRedirect()
            ->assertSessionHasErrors('settings.retry.max_attempts');

        $this->assertSame(3, config('webhook-manager.retry.max_attempts'));
    }

    public function test_it_saves_a_list_setting_as_a_trimmed_compacted_list(): void
    {
        $this->patchSettings(['logging.mask_payload_keys' => ['password', ' iban ', '']])->assertRedirect();

        // The control is a textarea of lines, and a trailing newline must not
        // become a masking rule for the empty key.
        $this->assertSame(['password', 'iban'], config('webhook-manager.logging.mask_payload_keys'));
    }

    public function test_a_saved_status_list_still_triggers_a_retry(): void
    {
        // A textarea hands back strings, and the planner compares a saved
        // status list against a real response status with a strict `in_array`
        // — `"429"` never equals `429`, so a stringified list silently stops
        // retrying anything, which looks exactly like a healthy endpoint.
        //
        // `settingsGroups()` declares `items => 'integer'` for this field, and
        // since brand-context 1.12.0 the layer honours it: the lines come back
        // as integers. Asserted here rather than trusted, because the failure
        // it prevents is invisible from the outside.
        $this->patchSettings(['retry.retry_on_status' => ['429', '503']])->assertRedirect();

        $this->assertSame([429, 503], config('webhook-manager.retry.retry_on_status'));

        // Unsaved on purpose: the planner reads attributes, not rows, and the
        // hook carries no `retry_strategy` of its own so the config decides.
        $hook = new OutboundWebhook;
        $delivery = new Delivery(['attempts' => 1]);

        // `ok => true` on purpose: without it the classifier calls this an
        // internal failure and the planner never reaches the status list at
        // all, so the test would pass with the list ignored entirely.
        $this->assertNotNull(
            app(RetryPlanner::class)->plan($delivery, $hook, ['ok' => true, 'status' => 429]),
            'A 429 is on the saved retry list but no retry was planned.',
        );

        // And a status that is not on the list still gets none, or the
        // assertion above would hold for any response.
        $this->assertNull(
            app(RetryPlanner::class)->plan($delivery, $hook, ['ok' => true, 'status' => 418]),
        );
    }

    public function test_an_integer_list_returns_to_its_packaged_default(): void
    {
        // This was written as a known gap and is now the assertion that the
        // gap is closed. Until brand-context 1.12.0 the layer validated every
        // line of a list as a string and stored it as one, so a field whose
        // packaged default holds integers — `[500, 502, 503, 504]` here —
        // could never satisfy "value equals the packaged default, delete the
        // row". A row was written on the first save and stayed forever: the
        // setting was pinned, a later release changing the shipped status list
        // would not reach an install that once pressed Save, and nothing on
        // screen said so.
        //
        // With `items => 'integer'` in `settingsGroups()` the layer keeps the
        // type, so saving the form untouched stores nothing at all.
        $this->patchSettings([])->assertRedirect();

        $this->assertSame(
            0,
            $this->rowCount('retry.retry_on_status'),
            'Saving the form untouched pinned the status list instead of leaving it on the packaged default.',
        );
        $this->assertSame(0, $this->rowCount('retry.max_attempts'));
    }

    public function test_it_refuses_zero_delivery_attempts(): void
    {
        $this->patchSettings(['retry.max_attempts' => 0])
            ->assertSessionHasErrors('settings.retry.max_attempts');

        $this->assertSame(3, config('webhook-manager.retry.max_attempts'));
    }

    public function test_it_allows_a_rate_limit_of_zero_because_that_means_no_throttling(): void
    {
        $this->patchSettings(['inbound.rate_limit_per_minute' => 0])->assertRedirect();

        $this->assertSame(0, config('webhook-manager.inbound.rate_limit_per_minute'));
    }

    public function test_it_ignores_a_key_the_settings_definition_does_not_offer(): void
    {
        // `storage.driver` decides where the webhook configuration lives and is
        // switched through the migrator, not through this form. A row for it
        // must not be creatable through this endpoint, and must not reach
        // `config()` even if one somehow existed.
        $this->patchSettings(['storage.driver' => 'flat'])->assertRedirect();

        $this->assertFalse(
            BrandSetting::query()->where('namespace', 'webhook-manager')->where('key', 'storage.driver')->exists()
        );
        $this->assertSame('eloquent', config('webhook-manager.storage.driver'));
    }

    public function test_it_refuses_the_write_without_the_settings_permission(): void
    {
        $this->patchSettings(
            ['retry.max_attempts' => 5],
            $this->cpUser(['view webhooks', 'manage outbound webhooks']),
        )->assertStatus(403);

        $this->assertSame(0, BrandSetting::query()->where('namespace', 'webhook-manager')->count());
        $this->assertSame(3, config('webhook-manager.retry.max_attempts'));
    }

    public function test_the_shared_screen_hides_the_section_without_the_permission(): void
    {
        // Statamic core hides what you cannot reach rather than greying it out,
        // and a read-only section invites a question nobody on the site can
        // answer. Asserted from this side too: the addon declares the
        // permission, so the addon is where a wrong one would be noticed.
        $sections = $this->actingAs($this->cpUser(['view webhooks']))
            ->withHeaders($this->inertiaHeaders())
            ->get(cp_route('brand-context.settings.index'))
            ->assertOk()
            ->json('props.sections');

        $this->assertSame([], array_filter(
            $sections,
            fn (array $section) => $section['namespace'] === 'webhook-manager',
        ));
    }

    public function test_it_hands_the_page_the_form_definition_and_the_current_values(): void
    {
        $this->patchSettings(['retry.max_attempts' => 5])->assertRedirect();

        $section = $this->section();

        $this->assertNotEmpty($section['groups']);
        $this->assertSame(5, $section['values']['retry.max_attempts']);

        // Every field the form draws has a value handed to it. A field without
        // one renders an empty control that saves an empty value over a good
        // default the first time somebody presses Save.
        foreach ($section['groups'] as $group) {
            foreach ($group['fields'] as $field) {
                $this->assertArrayHasKey($field['key'], $section['values']);
            }
        }
    }

    public function test_every_field_group_and_option_is_actually_translated(): void
    {
        // `Settings::field()` flattens `retry.retry_on_status` to the lang key
        // `retry_retry_on_status`, and the lang files had it as
        // `retry_on_status`. The result was not a fallback or an empty label:
        // Laravel returns the key itself, so the Retry panel printed
        // "webhook-manager::settings.fields.retry_retry_on_status.label" on
        // screen, in both languages, and the validation message for that field
        // read the same. 285 green tests never looked at a label.
        //
        // Checked against both languages, because a key can exist in one file
        // and not the other, and against the group headings and select options
        // too — they are looked up by the same convention and fail the same way.
        foreach (['en', 'de'] as $locale) {
            app()->setLocale($locale);

            foreach (Settings::settingsGroups() as $group) {
                $this->assertUntranslatedKeysAbsent($group, $locale);

                foreach ($group['fields'] as $field) {
                    $this->assertUntranslatedKeysAbsent($field, $locale);

                    foreach ($field['options'] ?? [] as $option) {
                        $this->assertUntranslatedKeysAbsent($option, $locale);
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function assertUntranslatedKeysAbsent(array $item, string $locale): void
    {
        foreach (['title', 'label', 'description'] as $slot) {
            $value = $item[$slot] ?? null;

            if (! is_string($value)) {
                continue;
            }

            $this->assertStringNotContainsString(
                'webhook-manager::',
                $value,
                sprintf('Untranslated %s in %s: %s', $slot, $locale, $value),
            );
        }
    }

    public function test_it_applies_stored_settings_on_a_fresh_boot(): void
    {
        BrandSetting::query()->create([
            'brand_id' => app('brand-context')->currentId(),
            'namespace' => 'webhook-manager',
            'key' => 'features.inbound',
            'value' => false,
        ]);

        // The overrides are read once and cached; a queue worker booting later
        // must still see them, which is the whole reason the shared manager
        // applies from `booted()` rather than from a Control-Panel middleware.
        $settings = app(SettingsManager::class);
        $settings->forget('webhook-manager');
        $settings->apply(force: true);

        $this->assertFalse(config('webhook-manager.features.inbound'));
    }

    public function test_a_row_left_over_from_an_older_release_cannot_reach_the_config(): void
    {
        // `storage.driver` and the alert credentials are one string away from
        // an arbitrary config path, so a row for a key the addon no longer
        // offers must be inert rather than authoritative.
        BrandSetting::query()->create([
            'brand_id' => app('brand-context')->currentId(),
            'namespace' => 'webhook-manager',
            'key' => 'storage.driver',
            'value' => 'flat',
        ]);

        $settings = app(SettingsManager::class);
        $settings->forget('webhook-manager');
        $settings->apply(force: true);

        $this->assertSame('eloquent', config('webhook-manager.storage.driver'));
    }
}
