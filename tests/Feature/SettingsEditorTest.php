<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Domain\Settings\Models\WebhookSetting;
use Goldnead\WebhookManager\Support\Settings;
use Goldnead\WebhookManager\Tests\CpTestCase;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

/**
 * The settings screen writes, and what it writes reaches the config.
 *
 * The screen was read-only until now: it printed `config/webhook-manager.php`
 * and told the operator to go and edit a file on the server. Everything here is
 * about the two properties that make the replacement trustworthy — a saved
 * value is the value the rest of the addon reads, and a value returned to its
 * default stops being stored at all.
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
     * a 200 with a JSON body and not a 422. That is the whole point of the
     * change: the CP gets its progress bar, its toast and its dirty guard, and
     * the page is re-rendered so every prop on it (not only the form) reflects
     * the write.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function patchSettings(array $overrides, ?Authenticatable $as = null): TestResponse
    {
        $settings = [];

        foreach (array_keys(Settings::fields()) as $key) {
            $settings[$key] = config('webhook-manager.'.$key);
        }

        $request = $as ? $this->actingAs($as) : $this;

        return $request
            ->withHeaders($this->inertiaHeaders())
            ->patch(
                cp_route('webhook-manager.settings.update'),
                ['settings' => array_replace($settings, $overrides)],
            );
    }

    /** The props the settings page hands the form on a fresh render. */
    protected function settingsProps(): array
    {
        return $this->withHeaders($this->inertiaHeaders())
            ->get(cp_route('webhook-manager.settings'))
            ->assertOk()
            ->json('props');
    }

    public function test_it_stores_a_changed_setting_and_applies_it_to_the_config(): void
    {
        $this->patchSettings(['retry.max_attempts' => 5])->assertRedirect();

        $this->assertSame(5, WebhookSetting::where('key', 'retry.max_attempts')->first()?->value);
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
        $this->assertSame(1, WebhookSetting::count());

        // Not "stores 3" — stores nothing. A row pinning a value to what it
        // already was would freeze that default across package upgrades.
        $this->patchSettings(['retry.max_attempts' => 3])->assertRedirect();

        $this->assertSame(0, WebhookSetting::count());

        // And the running application has to agree in the same breath.
        // `apply()` only writes the overrides that exist, so a deleted one used
        // to leave the old value standing until the next boot: the row gone,
        // the screen saying "default", and every reader still getting 5.
        $this->assertSame(3, config('webhook-manager.retry.max_attempts'));
    }

    public function test_the_page_it_redirects_to_shows_the_settings_as_they_now_stand(): void
    {
        // Keyed by the dotted path, flat — the same shape the form indexes by,
        // so the screen can take the answer without knowing the config nesting.
        // Read off the re-rendered page rather than out of a JSON body: the
        // point of redirecting back is that the whole page is rebuilt, not
        // just the form.
        $this->patchSettings([
            'retry.max_attempts' => '5',
            'logging.mask_headers' => ['authorization', ' cookie ', ''],
        ])->assertRedirect();

        $values = $this->settingsProps()['values'];

        $this->assertSame(5, $values['retry.max_attempts']);
        $this->assertSame(['authorization', 'cookie'], $values['logging.mask_headers']);
    }

    public function test_it_says_so_when_the_settings_were_saved(): void
    {
        // The screen dropped its own success banner when saving moved onto the
        // Inertia router: the confirmation is now core's flash toast, which
        // only appears if the controller actually flashes one.
        $this->patchSettings(['retry.max_attempts' => 5])
            ->assertRedirect()
            ->assertSessionHas('success', __('webhook-manager::settings.saved'));
    }

    public function test_a_rejected_field_comes_back_in_the_error_bag(): void
    {
        // An Inertia visit carries validation failures in the session error
        // bag, keyed the same way the form indexes its controls, and `useForm`
        // / `router`'s `onError` hands them straight to the field. A 422 with
        // a JSON body would arrive as an unhandled rejection instead.
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

    public function test_it_stores_retry_status_codes_as_integers(): void
    {
        // They are compared against a real response status, and `"429"` never
        // equals `429`.
        $this->patchSettings(['retry.retry_on_status' => ['429', '503']])->assertRedirect();

        $this->assertSame([429, 503], config('webhook-manager.retry.retry_on_status'));
    }

    public function test_it_refuses_zero_delivery_attempts(): void
    {
        $this->patchSettings(['retry.max_attempts' => 0])
            ->assertSessionHasErrors('settings.retry.max_attempts');

        $this->assertSame(3, config('webhook-manager.retry.max_attempts'));
    }

    public function test_it_refuses_a_strategy_that_is_not_one_of_the_offered_ones(): void
    {
        $this->patchSettings(['retry.strategy' => 'whenever'])
            ->assertSessionHasErrors('settings.retry.strategy');

        $this->assertSame('exponential', config('webhook-manager.retry.strategy'));
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

        $this->assertFalse(WebhookSetting::where('key', 'storage.driver')->exists());
        $this->assertSame('eloquent', config('webhook-manager.storage.driver'));
    }

    public function test_it_refuses_the_write_without_the_settings_permission(): void
    {
        $this->patchSettings(
            ['retry.max_attempts' => 5],
            $this->cpUser(['view webhooks', 'manage outbound webhooks']),
        )->assertStatus(403);

        $this->assertSame(0, WebhookSetting::count());
        $this->assertSame(3, config('webhook-manager.retry.max_attempts'));
    }

    public function test_it_hands_the_page_the_form_definition_and_the_current_values(): void
    {
        $this->patchSettings(['retry.max_attempts' => 5])->assertRedirect();

        $props = $this->settingsProps();

        $this->assertNotEmpty($props['groups']);
        $this->assertSame(5, $props['values']['retry.max_attempts']);

        // Every field the form draws has a value handed to it. A field without
        // one renders an empty control that saves an empty value over a good
        // default the first time somebody presses Save.
        foreach ($props['groups'] as $group) {
            foreach ($group['fields'] as $field) {
                $this->assertArrayHasKey($field['key'], $props['values']);
            }
        }
    }

    public function test_it_does_not_print_the_alert_credential_into_the_page(): void
    {
        // The diagnostics panel prints the resolved config tree so an operator
        // can see what the installation actually resolved to. The chat alert
        // URL *is* the credential — anybody holding it can post into that
        // channel — and printed verbatim it lands in screen shares,
        // screenshots and every front-end error report.
        //
        // Asserted against the whole page payload, not against the one prop:
        // a secret that reappears somewhere else in the props is the same
        // leak, and a test aimed at `rawConfig` alone would miss it.
        $url = 'https://hooks.slack.com/services/T000/B000/xoxbSuperSecretValue';
        config()->set('webhook-manager.alerts.slack.webhook_url', $url);

        $response = $this->withHeaders($this->inertiaHeaders())
            ->get(cp_route('webhook-manager.settings'))
            ->assertOk();

        $this->assertStringNotContainsString($url, $response->getContent());
        $this->assertStringNotContainsString('xoxbSuperSecretValue', $response->getContent());

        // Masked, not dropped. The operator still has to be able to tell that
        // a value is set and to recognise which one it is.
        $raw = $response->json('props.rawConfig');
        $this->assertStringContainsString('http', $raw);
        $this->assertStringContainsString('alue', $raw);
        $this->assertStringContainsString('\u2022', $raw);
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

            foreach (Settings::groups() as $group) {
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

    public function test_it_does_not_bake_overrides_into_a_cached_config(): void
    {
        // `config:cache` boots the app and dumps the resolved config to disk.
        // An override written into that dump outlives the row it came from:
        // deleting the setting afterwards has no effect at all until somebody
        // runs `config:clear`. It also poisons the "back to default" rule —
        // the next boot reads the baked file as the packaged default, so a
        // value reset to the file's own default is stored as a row instead of
        // being deleted, and that key is then stuck for good.
        WebhookSetting::create(['key' => 'retry.max_attempts', 'value' => 9]);

        $packaged = config('webhook-manager.retry.max_attempts');

        $settings = app(Settings::class);
        $settings->forget();

        // What the config-cache build looks like from in here.
        $argv = $_SERVER['argv'] ?? [];
        $_SERVER['argv'] = ['artisan', 'config:cache'];

        try {
            $settings->apply();
        } finally {
            $_SERVER['argv'] = $argv;
        }

        $this->assertSame(
            $packaged,
            config('webhook-manager.retry.max_attempts'),
            'The config-cache build must dump the file value, not the override.',
        );
    }

    public function test_it_applies_stored_settings_on_a_fresh_boot(): void
    {
        WebhookSetting::create(['key' => 'features.inbound', 'value' => false]);

        // The overrides are read once and cached; a queue worker booting later
        // must still see them, which is the whole reason apply() runs in
        // bootAddon rather than in a Control-Panel middleware.
        app(Settings::class)->forget();
        app(Settings::class)->apply();

        $this->assertFalse(config('webhook-manager.features.inbound'));
    }
}
