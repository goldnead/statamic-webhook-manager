<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Tests\CpTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The "Objekt" column names what a delivery was about.
 *
 * The suite bridges write subject types the manager had no word for
 * (subscription, invoice, commission, seat_pool …). The column then showed
 * `ucfirst()` of the key: "Commission" in a German CP and "Seat_pool" with its
 * underscore. Every type the suite sends now has a German and an English
 * label, and a type nobody translated shows its handle as it is.
 */
class SubjectTypeLabelsTest extends CpTestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private function suiteTypes(): array
    {
        return ['subscription', 'invoice', 'commission', 'course', 'funnel', 'seat', 'seat_pool', 'offer', 'coupon', 'partner'];
    }

    public function test_every_suite_subject_type_is_translated_in_both_locales(): void
    {
        foreach (['de', 'en'] as $locale) {
            foreach ($this->suiteTypes() as $type) {
                $key = 'webhook-manager::messages.subject_types.'.$type;
                $this->assertNotSame($key, __($key, [], $locale), "{$locale}: {$type} has no label");
            }
        }

        app()->setLocale('de');
        $this->assertSame('Provision', __('webhook-manager::messages.subject_types.commission'));
        $this->assertSame('Platzkontingent', __('webhook-manager::messages.subject_types.seat_pool'));
    }

    private function rowFor(string $type): array
    {
        $hook = OutboundWebhook::create([
            'name' => 'Label hook',
            'handle' => 'label-hook-'.uniqid(),
            'enabled' => true,
            'trigger_type' => 'payments.paid',
            'url' => 'https://receiver.example.test/hook',
            'method' => 'POST',
            'auth_type' => 'none',
            'payload_type' => 'raw_json',
            'queue_enabled' => false,
        ]);

        $delivery = Delivery::create([
            'outbound_webhook_id' => $hook->id,
            'trigger_type' => 'payments.paid',
            'status' => Delivery::STATUS_SUCCESS,
            'request_url' => 'https://receiver.example.test/hook',
            'request_method' => 'POST',
            'request_headers' => [],
            'request_body' => '{}',
            'response_status' => 200,
            'attempts' => 1,
            'subject_type' => $type,
            'subject_id' => '5',
        ]);

        $response = $this->actingAs($this->superUser())
            ->getJson(cp_route('webhook-manager.deliveries.index'));

        $response->assertOk();

        return collect($response->json('data'))->firstWhere('id', $delivery->id);
    }

    public function test_the_listing_shows_the_translated_label_for_a_suite_type(): void
    {
        app()->setLocale('de');

        $this->assertSame('Abo', $this->rowFor('subscription')['subject_label']);
    }

    public function test_an_untranslated_type_shows_its_handle_unchanged(): void
    {
        $this->assertSame('widget_part', $this->rowFor('widget_part')['subject_label']);
    }
}
