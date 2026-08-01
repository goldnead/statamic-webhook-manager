<?php

namespace Goldnead\WebhookManager\Registries;

use Goldnead\WebhookManager\Contracts\InboundActionHandlerInterface;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Handlers\AuditLogHandler;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Handlers\CreateEntryHandler;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Handlers\CreateFormSubmissionHandler;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Handlers\DispatchEventHandler;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Handlers\NoopHandler;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Handlers\UpdateEntryHandler;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Handlers\UpsertEntryHandler;
use Goldnead\WebhookManager\Domain\InboundEndpoint\Handlers\UpsertLeadHandler;

/**
 * Registry for inbound action handlers.
 *
 * Inbound action handlers are kept separate from outbound rule
 * `ActionInterface` actions because the input/output shape is different —
 * inbound handlers receive a mapped payload + raw payload + endpoint and
 * return a structured `{ok, message, data}` array suitable for the
 * inbound HTTP response.
 *
 * Built-ins are registered in `WebhookManagerServiceProvider::bootRegistries()`;
 * third parties can register custom handlers via the `WebhookManager` facade.
 */
class InboundActionHandlerRegistry
{
    /** @var array<string, InboundActionHandlerInterface> */
    protected array $handlers = [];

    public function register(InboundActionHandlerInterface $handler): void
    {
        $this->handlers[$handler->handle()] = $handler;
    }

    public function get(string $handle): ?InboundActionHandlerInterface
    {
        return $this->handlers[$handle] ?? null;
    }

    /** @return array<string, InboundActionHandlerInterface> */
    public function all(): array
    {
        return $this->handlers;
    }

    /**
     * Map of handle => label for CP select inputs.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $opts = [];
        foreach ($this->handlers as $h) {
            $opts[$h->handle()] = $h->label();
        }

        return $opts;
    }

    public function registerDefaults(): void
    {
        $this->register(new NoopHandler);
        $this->register(new CreateEntryHandler);
        $this->register(new UpdateEntryHandler);
        $this->register(new UpsertEntryHandler);
        $this->register(new CreateFormSubmissionHandler);
        $this->register(new DispatchEventHandler);
        $this->register(new AuditLogHandler);
        // Self-guards on LeadHub presence, so registering it unconditionally is safe.
        $this->register(new UpsertLeadHandler);
    }
}
