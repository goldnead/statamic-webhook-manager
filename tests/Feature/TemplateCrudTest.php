<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Domain\Template\Actions\CreateTemplateAction;
use Goldnead\WebhookManager\Domain\Template\Actions\DeleteTemplateAction;
use Goldnead\WebhookManager\Domain\Template\Actions\UpdateTemplateAction;
use Goldnead\WebhookManager\Domain\Template\Models\Template;
use Goldnead\WebhookManager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Exercises the Template domain action layer (Create / Update / Delete).
 * Doesn't go through the CP HTTP layer because that would require a
 * Statamic-authenticated user — the action layer is the boundary the
 * controllers thinly delegate to and is what we want to lock down.
 */
class TemplateCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_template_action_persists_with_defaults(): void
    {
        $template = $this->app->make(CreateTemplateAction::class)([
            'name' => 'CRM payload',
            'body' => '{ "id": "{{ entry:id }}" }',
        ]);

        $this->assertNotNull($template->id);
        $this->assertSame('CRM payload', $template->name);
        $this->assertSame('crm-payload', $template->handle);
        $this->assertSame(Template::TYPE_OUTBOUND_BODY, $template->type);
        $this->assertSame('{ "id": "{{ entry:id }}" }', $template->body);
        $this->assertNotEmpty($template->uuid);
    }

    public function test_create_template_action_respects_explicit_handle_and_type(): void
    {
        $template = $this->app->make(CreateTemplateAction::class)([
            'name' => 'Discord notice',
            'handle' => 'discord-notice',
            'type' => Template::TYPE_NOTIFICATION,
            'body' => 'Hello!',
        ]);

        $this->assertSame('discord-notice', $template->handle);
        $this->assertSame(Template::TYPE_NOTIFICATION, $template->type);
    }

    public function test_update_template_action_changes_attributes(): void
    {
        $template = Template::create([
            'name' => 'Initial',
            'handle' => 'initial',
            'type' => Template::TYPE_OUTBOUND_BODY,
            'body' => 'old body',
        ]);

        $updated = $this->app->make(UpdateTemplateAction::class)($template, [
            'name' => 'Updated',
            'body' => 'new body',
        ]);

        $this->assertSame('Updated', $updated->name);
        $this->assertSame('new body', $updated->body);
        $this->assertSame('initial', $updated->handle, 'handle is preserved unless explicitly changed');
    }

    public function test_delete_template_action_detaches_referencing_outbound_webhooks(): void
    {
        $template = Template::create([
            'name' => 'Lib body',
            'handle' => 'lib-body',
            'type' => Template::TYPE_OUTBOUND_BODY,
            'body' => '{ "ok": true }',
        ]);

        OutboundWebhook::create([
            'name' => 'Hook A',
            'handle' => 'hook-a',
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'url' => 'https://example.com/a',
            'method' => 'POST',
            'auth_type' => 'none',
            'payload_type' => 'raw_json',
            'payload_template_handle' => 'lib-body',
        ]);
        OutboundWebhook::create([
            'name' => 'Hook B',
            'handle' => 'hook-b',
            'enabled' => true,
            'trigger_type' => 'entry.published',
            'url' => 'https://example.com/b',
            'method' => 'POST',
            'auth_type' => 'none',
            'payload_type' => 'raw_json',
            'payload_template_handle' => 'lib-body',
        ]);

        $result = $this->app->make(DeleteTemplateAction::class)($template);

        $this->assertTrue($result['deleted']);
        $this->assertSame(2, $result['detached_outbounds']);
        $this->assertNull(OutboundWebhook::where('handle', 'hook-a')->first()->payload_template_handle);
        $this->assertNull(OutboundWebhook::where('handle', 'hook-b')->first()->payload_template_handle);
        $this->assertNull(Template::find($template->id));
    }

    public function test_delete_template_action_with_no_references_reports_zero_detached(): void
    {
        $template = Template::create([
            'name' => 'Lonely',
            'handle' => 'lonely',
            'type' => Template::TYPE_OUTBOUND_BODY,
            'body' => 'x',
        ]);

        $result = $this->app->make(DeleteTemplateAction::class)($template);

        $this->assertTrue($result['deleted']);
        $this->assertSame(0, $result['detached_outbounds']);
    }
}
