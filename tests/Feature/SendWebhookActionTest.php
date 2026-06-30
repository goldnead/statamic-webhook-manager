<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Actions\Cp\SendWebhook;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class SendWebhookActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_running_the_action_fires_the_chosen_webhook_for_each_entry(): void
    {
        Http::fake([
            'https://example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $hook = OutboundWebhook::create([
            'name' => 'Manual sender',
            'handle' => 'manual-sender',
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'url' => 'https://example.com/webhook',
            'method' => 'POST',
            'auth_type' => 'none',
            'payload_type' => 'raw_json',
            'payload_template' => '{"id":"{{ entry:id }}","title":"{{ entry:title }}"}',
            'queue_enabled' => false,
        ]);

        // StatamicSnapshot::entry() accepts arrays as-is, so we can drive the
        // action with plain entry-shaped data without booting the Stache.
        $entries = collect([
            ['id' => '1', 'title' => 'First', 'site' => 'default'],
            ['id' => '2', 'title' => 'Second', 'site' => 'default'],
        ]);

        $action = new SendWebhook();
        $action->items($entries);
        $result = $action->run($entries, ['webhook' => $hook->uuid]);

        $this->assertSame(2, Delivery::count());
        $this->assertSame(2, Delivery::where('status', Delivery::STATUS_SUCCESS)->count());

        $first = Delivery::orderBy('id')->first();
        $this->assertStringContainsString('"id":"1"', $first->request_body);
        $this->assertStringContainsString('"title":"First"', $first->request_body);
        $this->assertStringContainsString('Manual sender', (string) $result);
    }

    public function test_visible_only_when_an_enabled_webhook_exists(): void
    {
        $action = new SendWebhook();

        // No webhooks yet → hidden even for an entry-like item.
        $entry = \Mockery::mock(\Statamic\Contracts\Entries\Entry::class);
        $this->assertFalse($action->visibleTo($entry));

        OutboundWebhook::create([
            'name' => 'X', 'handle' => 'x', 'enabled' => true,
            'trigger_type' => 'entry.published', 'url' => 'https://example.com/x',
            'method' => 'POST', 'auth_type' => 'none', 'payload_type' => 'raw_json',
        ]);

        $this->assertTrue($action->visibleTo($entry));
        $this->assertFalse($action->visibleTo(['not' => 'an entry']));
    }
}
