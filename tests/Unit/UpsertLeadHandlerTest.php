<?php

namespace Goldnead\WebhookManager\Tests\Unit;

use Goldnead\WebhookManager\Domain\InboundEndpoint\Handlers\UpsertLeadHandler;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Models\InboundEndpoint;
use Goldnead\WebhookManager\Registries\InboundActionHandlerRegistry;
use Goldnead\WebhookManager\Tests\TestCase;

class UpsertLeadHandlerTest extends TestCase
{
    /**
     * The FQCN the handler resolves the LeadHub facade by. This is the
     * integration contract with goldnead/statamic-leadhub — if it changes
     * there, it must change here (note the lowercase "hub" PSR-4 namespace).
     */
    private const LEADHUB_FACADE = 'Goldnead\\Leadhub\\Facades\\LeadHub';

    public function test_the_leadhub_facade_fqcn_contract_is_stable(): void
    {
        $constant = (new \ReflectionClass(UpsertLeadHandler::class))
            ->getConstant('LEADHUB_FACADE');

        $this->assertSame('\\'.self::LEADHUB_FACADE, $constant);
    }

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
        if (class_exists('\\'.self::LEADHUB_FACADE)) {
            $this->markTestSkipped('A LeadHub facade class is present; the absent-path cannot be exercised in this process.');
        }

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

    public function test_ingest_mode_calls_ingest_and_find_by_email_when_leadhub_is_present(): void
    {
        $this->defineFakeLeadHubFacade();

        $endpoint = new InboundEndpoint(['action_config' => [
            'type' => 'payment.received',
            'source' => 'stripe',
        ]]);

        $result = (new UpsertLeadHandler())->handleAction(
            $endpoint,
            ['email' => 'buyer@example.com', 'first_name' => 'Ada'],
            [],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame('lead-1', $result['data']['lead_id']);

        $calls = $this->fakeLeadHubCalls();
        $this->assertSame(['ingest', 'findByEmail'], array_column($calls, 0));

        $ingestPayload = $calls[0][1][0];
        $this->assertSame('buyer@example.com', $ingestPayload['email']);
        $this->assertSame('payment.received', $ingestPayload['type']);
        $this->assertSame('stripe', $ingestPayload['source']);
        $this->assertSame(['first_name' => 'Ada'], $ingestPayload['contact']);

        $this->assertSame(['buyer@example.com'], $calls[1][1]);
    }

    public function test_upsert_mode_calls_create_and_projects_a_pipeline_opportunity(): void
    {
        $this->defineFakeLeadHubFacade();

        $endpoint = new InboundEndpoint(['action_config' => [
            'mode' => 'upsert',
            'pipeline' => 'sales',
        ]]);

        $result = (new UpsertLeadHandler())->handleAction(
            $endpoint,
            ['email' => 'buyer@example.com', 'value' => 99],
            [],
        );

        $this->assertTrue($result['ok']);

        $calls = $this->fakeLeadHubCalls();
        $this->assertSame(['create', 'upsertOpportunity'], array_column($calls, 0));

        $this->assertSame('buyer@example.com', $calls[0][1][0]['email']);

        [$leadId, $pipeline, $attrs] = $calls[1][1];
        $this->assertSame('lead-1', $leadId);
        $this->assertSame('sales', $pipeline);
        $this->assertSame(99, $attrs['value_estimate']);
    }

    /**
     * Defines a spy class at the exact FQCN the handler targets, so the
     * positive path (class exists → upsert methods are called) is locked
     * without a hard dependency on goldnead/statamic-leadhub.
     */
    private function defineFakeLeadHubFacade(): void
    {
        if (! class_exists('\\'.self::LEADHUB_FACADE)) {
            eval(<<<'PHP'
                namespace Goldnead\Leadhub\Facades;

                class LeadHub
                {
                    public static array $calls = [];

                    public static function __callStatic(string $name, array $args)
                    {
                        static::$calls[] = [$name, $args];

                        return match ($name) {
                            'create', 'findByEmail' => ['id' => 'lead-1'],
                            'ingest' => ['id' => 'event-1'],
                            default => null,
                        };
                    }
                }
                PHP);
        }

        $facade = '\\'.self::LEADHUB_FACADE;
        $facade::$calls = [];
    }

    /** @return array<int, array{0: string, 1: array}> */
    private function fakeLeadHubCalls(): array
    {
        $facade = '\\'.self::LEADHUB_FACADE;

        return $facade::$calls;
    }
}
