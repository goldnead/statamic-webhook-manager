<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Registries\TriggerRegistry;
use Goldnead\WebhookManager\Tests\CpTestCase;
use Goldnead\WebhookManager\Triggers\CustomEventTrigger;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The trigger picker, read by someone with sixty triggers to choose from.
 *
 * Six suite addons register their moments here. A flat list sorted by handle
 * put "affiliates.commission_earned" at the top of every new hook and mixed
 * "Eintrag — gespeichert" with "Zahlungen: Zahlung eingegangen". The picker
 * now gets its options grouped by the trigger's source type, each group with
 * a translated heading, sorted by heading and then by label, and every label
 * in one form: "<Gruppe>: <Moment>".
 */
class TriggerPickerGroupsTest extends CpTestCase
{
    use RefreshDatabase;

    private function registry(): TriggerRegistry
    {
        return $this->app->make(TriggerRegistry::class);
    }

    public function test_grouped_options_carry_the_source_type_and_a_translated_heading(): void
    {
        app()->setLocale('de');

        $choices = collect($this->registry()->groupedOptions());
        $saved = $choices->firstWhere('value', 'entry.saved');

        $this->assertSame('entry', $saved['group']);
        $this->assertSame('Einträge', $saved['group_label']);
        $this->assertSame('Eintrag: gespeichert', $saved['label']);
        $this->assertSame('Gespeichert', $saved['short_label']);
    }

    public function test_groups_are_sorted_by_heading_and_members_by_label(): void
    {
        app()->setLocale('de');
        $registry = $this->registry();
        $registry->register(new CustomEventTrigger('payments.refunded', 'Zahlungen: Zahlung erstattet', 'payments'));
        $registry->register(new CustomEventTrigger('payments.paid', 'Zahlungen: Zahlung eingegangen', 'payments'));
        $registry->register(new CustomEventTrigger('affiliates.partner_approved', 'Partner: freigegeben', 'affiliates'));

        $choices = collect($registry->groupedOptions());

        $headings = $choices->pluck('group_label')->unique()->values()->all();
        $sorted = $headings;
        sort($sorted, SORT_NATURAL | SORT_FLAG_CASE);
        $this->assertSame($sorted, $headings, 'Groups must follow their heading, not the handle.');

        $payments = $choices->where('group', 'payments')->pluck('value')->values()->all();
        $this->assertSame(['payments.paid', 'payments.refunded'], $payments);
        $this->assertSame('Zahlungen', $choices->firstWhere('value', 'payments.paid')['group_label']);
        $this->assertSame('Partner', $choices->firstWhere('value', 'affiliates.partner_approved')['group_label']);
    }

    public function test_an_unknown_source_type_without_a_shared_prefix_shows_its_handle(): void
    {
        $registry = $this->registry();
        $registry->register(new CustomEventTrigger('acme.one', 'First thing', 'acme_widget'));
        $registry->register(new CustomEventTrigger('acme.two', 'Other thing', 'acme_widget'));

        $one = collect($registry->groupedOptions())->firstWhere('value', 'acme.one');

        $this->assertSame('acme_widget', $one['group_label']);
        $this->assertSame('First thing', $one['short_label']);
    }

    public function test_an_unknown_source_type_takes_the_prefix_its_labels_share(): void
    {
        $registry = $this->registry();
        $registry->register(new CustomEventTrigger('shop.one', 'Shop: order placed', 'shop_order'));
        $registry->register(new CustomEventTrigger('shop.two', 'Shop: order shipped', 'shop_order'));

        $one = collect($registry->groupedOptions())->firstWhere('value', 'shop.one');

        $this->assertSame('Shop', $one['group_label']);
        $this->assertSame('Order placed', $one['short_label']);
    }

    public function test_core_trigger_labels_use_a_colon_and_no_dash_in_every_locale(): void
    {
        foreach (['de', 'en'] as $locale) {
            app()->setLocale($locale);

            foreach ($this->registry()->options() as $handle => $label) {
                if (! in_array($handle, ['entry.saved', 'entry.published', 'entry.unpublished', 'entry.deleted', 'form.submitted', 'user.saved', 'asset.saved'], true)) {
                    continue;
                }

                $this->assertStringNotContainsString('—', $label, "{$locale}: {$handle}");
                $this->assertMatchesRegularExpression('/^[^:]+: \S/u', $label, "{$locale}: {$handle}");
            }
        }
    }

    public function test_the_create_screen_leaves_the_trigger_empty_and_hands_over_the_groups(): void
    {
        $response = $this->actingAs($this->superUser())
            ->withHeaders($this->inertiaHeaders())
            ->get(cp_route('webhook-manager.outbound.create'));

        $response->assertOk();
        $this->assertNull($response->json('props.webhook.trigger_type'));

        $choices = collect($response->json('props.triggerChoices'));
        $this->assertNotEmpty($choices);
        $this->assertNotNull($choices->firstWhere('value', 'entry.saved')['group_label'] ?? null);
    }

    public function test_the_rule_create_screen_hands_over_the_groups(): void
    {
        $response = $this->actingAs($this->superUser())
            ->withHeaders($this->inertiaHeaders())
            ->get(cp_route('webhook-manager.rules.create'));

        $response->assertOk();
        $this->assertNotEmpty($response->json('props.triggerChoices'));
    }

    public function test_saving_without_a_trigger_is_rejected(): void
    {
        $response = $this->actingAs($this->superUser())
            ->post(cp_route('webhook-manager.outbound.store'), [
                'name' => 'No trigger',
                'handle' => 'no-trigger',
                'enabled' => true,
                'trigger_type' => null,
                'url' => 'https://receiver.example.test/hook',
                'method' => 'POST',
                'auth_type' => 'none',
                'payload_type' => 'raw_json',
            ]);

        $response->assertSessionHasErrors('trigger_type');
        $this->assertSame(0, OutboundWebhook::query()->where('handle', 'no-trigger')->count());
    }
}
