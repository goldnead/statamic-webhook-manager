<?php

namespace Goldnead\WebhookManager\Registries;

use Goldnead\WebhookManager\Contracts\ActionInterface;

/**
 * TODO: REVIEW — actions are scaffolded; the full set ships with the rule
 * engine iteration. Outbound delivery is currently dispatched directly
 * via Services\DeliveryEngine, not through the action registry.
 */
class ActionRegistry
{
    /** @var array<string, ActionInterface> */
    protected array $actions = [];

    public function register(ActionInterface $action): void
    {
        $this->actions[$action->handle()] = $action;
    }

    public function get(string $handle): ?ActionInterface
    {
        return $this->actions[$handle] ?? null;
    }

    public function all(): array
    {
        return $this->actions;
    }
}
