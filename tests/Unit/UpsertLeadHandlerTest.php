<?php

namespace Goldnead\WebhookManager\Tests\Unit;

use Goldnead\WebhookManager\Domain\InboundEndpoint\Handlers\UpsertLeadHandler;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Goldnead\WebhookManager\Registries\InboundActionHandlerRegistry;
use Goldnead\WebhookManager\Tests\TestCase;

class UpsertLeadHandlerTest extends TestCase
{
    public function test_handle_and_label(): void
    {
        $handler = new UpsertLeadHandler();

        $this->assertSame('upsert_lead', $handler->handle());
        $this->assertNotEmpty($handler->label());
    }

    public function test_it_is_registered_among_the_inbound_defaults(): void
    {
        $registry = new InboundActionHandlerRegistry();
        $registry->registerDefaults();

        $this->assertNotNull($registry->get('upsert_lead'));
        $this->assertInstanceOf(UpsertLeadHandler::class, $registry->get('upsert_lead'));
    }

    public function test_it_reports_a_graceful_error_when_leadhub_is_absent(): void
    {
        // LeadHub is not installed in this addon's test environment, so the
        // handler must degrade gracefully rather than fatally.
        $endpoint = new InboundEndpoint(['action_config' => []]);

        $result = (new UpsertLeadHandler())->handleAction(
            $endpoint,
            ['email' => 'buyer@example.com'],
            [],
        );

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('LeadHub', $result['message']);
    }
}
