<?php

namespace Goldnead\WebhookManager\Registries;

use Goldnead\WebhookManager\Contracts\InboundActionHandlerInterface;

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
        $this->register(new \Goldnead\WebhookManager\Domain\InboundEndpoint\Handlers\NoopHandler());
        $this->register(new \Goldnead\WebhookManager\Domain\InboundEndpoint\Handlers\CreateEntryHandler());
        $this->register(new \Goldnead\WebhookManager\Domain\InboundEndpoint\Handlers\UpdateEntryHandler());
        $this->register(new \Goldnead\WebhookManager\Domain\InboundEndpoint\Handlers\UpsertEntryHandler());
        $this->register(new \Goldnead\WebhookManager\Domain\InboundEndpoint\Handlers\CreateFormSubmissionHandler());
        $this->register(new \Goldnead\WebhookManager\Domain\InboundEndpoint\Handlers\DispatchEventHandler());
        $this->register(new \Goldnead\WebhookManager\Domain\InboundEndpoint\Handlers\AuditLogHandler());
    }
}
