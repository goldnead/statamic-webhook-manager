<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Domain\Log\Models\LogEntry;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Domain\Template\Models\Template;
use Goldnead\WebhookManager\Services\Http\HttpRequestFactory;
use Goldnead\WebhookManager\Tests\TestCase;
use Goldnead\WebhookManager\ValueObjects\ExecutionContext;
use Goldnead\WebhookManager\ValueObjects\TriggerEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Verifies the precedence rules in HttpRequestFactory::buildBody:
 *
 *   1. payload_template_handle (library) wins
 *   2. payload_template (inline) is the fallback
 *   3. JSON-encoded TriggerEvent is the last-resort default
 *
 * Tested directly against HttpRequestFactory so we don't need a network
 * mock — the request body is what we assert on.
 */
class OutboundUsesLibraryTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function context(array $payload): ExecutionContext
    {
        return new ExecutionContext(new TriggerEvent(
            triggerHandle: 'entry.published',
            sourceType: 'entry',
            sourceReference: '42',
            payload: $payload,
            site: 'default',
        ));
    }

    public function test_library_template_wins_when_handle_is_set(): void
    {
        Template::create([
            'name' => 'Lib',
            'handle' => 'lib',
            'type' => Template::TYPE_OUTBOUND_BODY,
            'body' => '{"id":"{{ payload:id }}","source":"library"}',
        ]);

        $hook = OutboundWebhook::create([
            'name' => 'Hook',
            'handle' => 'hook',
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'url' => 'https://example.com/x',
            'method' => 'POST',
            'auth_type' => 'none',
            'payload_type' => 'raw_json',
            'payload_template' => '{"id":"INLINE-{{ payload:id }}","source":"inline"}',
            'payload_template_handle' => 'lib',
        ]);

        $factory = $this->app->make(HttpRequestFactory::class);
        $request = $factory->build($hook, $this->context(['id' => 42]));

        $this->assertStringContainsString('"source":"library"', $request['body']);
        $this->assertStringContainsString('"id":"42"', $request['body']);
        $this->assertStringNotContainsString('inline', $request['body']);
    }

    public function test_inline_template_is_used_when_no_library_handle(): void
    {
        $hook = OutboundWebhook::create([
            'name' => 'Hook',
            'handle' => 'hook',
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'url' => 'https://example.com/x',
            'method' => 'POST',
            'auth_type' => 'none',
            'payload_type' => 'raw_json',
            'payload_template' => '{"id":"INLINE-{{ payload:id }}"}',
        ]);

        $factory = $this->app->make(HttpRequestFactory::class);
        $request = $factory->build($hook, $this->context(['id' => 42]));

        $this->assertStringContainsString('INLINE-42', $request['body']);
    }

    public function test_falls_back_to_inline_when_library_handle_missing_template(): void
    {
        // No template row created — the handle dangles.
        $hook = OutboundWebhook::create([
            'name' => 'Hook',
            'handle' => 'hook',
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'url' => 'https://example.com/x',
            'method' => 'POST',
            'auth_type' => 'none',
            'payload_type' => 'raw_json',
            'payload_template' => '{"id":"INLINE-{{ payload:id }}"}',
            'payload_template_handle' => 'does-not-exist',
        ]);

        $factory = $this->app->make(HttpRequestFactory::class);
        $request = $factory->build($hook, $this->context(['id' => 42]));

        $this->assertStringContainsString('INLINE-42', $request['body']);
    }

    public function test_dangling_template_handle_writes_configuration_error_log(): void
    {
        $hook = OutboundWebhook::create([
            'name' => 'Dangling',
            'handle' => 'dangling',
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'url' => 'https://example.com/x',
            'method' => 'POST',
            'auth_type' => 'none',
            'payload_type' => 'raw_json',
            'payload_template_handle' => 'does-not-exist',
        ]);

        $factory = $this->app->make(HttpRequestFactory::class);
        $factory->build($hook, $this->context(['id' => 42]));

        $entry = LogEntry::where('type', 'configuration_error_dangling_template')->first();
        $this->assertNotNull($entry, 'Dangling template should produce a log entry.');
        $this->assertSame('warning', $entry->level);
        $this->assertSame($hook->id, $entry->context['webhook_id']);
        $this->assertSame('does-not-exist', $entry->context['template_handle']);
        $this->assertSame('configuration', $entry->context['error_type']);
    }

    public function test_default_json_event_when_neither_template_set(): void
    {
        $hook = OutboundWebhook::create([
            'name' => 'Hook',
            'handle' => 'hook',
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'url' => 'https://example.com/x',
            'method' => 'POST',
            'auth_type' => 'none',
            'payload_type' => 'raw_json',
        ]);

        $factory = $this->app->make(HttpRequestFactory::class);
        $request = $factory->build($hook, $this->context(['id' => 42]));

        $decoded = json_decode($request['body'], true);
        $this->assertIsArray($decoded);
        $this->assertSame('entry.published', $decoded['trigger']);
        $this->assertSame(42, $decoded['payload']['id']);
    }

    public function test_template_change_propagates_to_subsequent_renders(): void
    {
        $template = Template::create([
            'name' => 'Lib',
            'handle' => 'lib',
            'type' => Template::TYPE_OUTBOUND_BODY,
            'body' => 'v1: {{ payload:id }}',
        ]);

        $hook = OutboundWebhook::create([
            'name' => 'Hook',
            'handle' => 'hook',
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'url' => 'https://example.com/x',
            'method' => 'POST',
            'auth_type' => 'none',
            'payload_type' => 'raw_json',
            'payload_template_handle' => 'lib',
        ]);

        $factory = $this->app->make(HttpRequestFactory::class);

        $first = $factory->build($hook, $this->context(['id' => 42]));
        $this->assertStringContainsString('v1: 42', $first['body']);

        // Operator updates the template — next render picks it up.
        $template->update(['body' => 'v2: {{ payload:id }}']);

        $second = $factory->build($hook->fresh(), $this->context(['id' => 99]));
        $this->assertStringContainsString('v2: 99', $second['body']);
    }
}
