<?php

namespace Goldnead\WebhookManager\Registries;

use Goldnead\WebhookManager\Contracts\ActionInterface;

/**
 * Registry of rule actions. Built-ins are registered in
 * `WebhookManagerServiceProvider::bootRegistries()`; third parties
 * register custom actions via `WebhookManager::registerAction(...)`.
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

    /** @return array<string, ActionInterface> */
    public function all(): array
    {
        return $this->actions;
    }

    /**
     * Map of handle => label for CP select inputs.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $opts = [];
        foreach ($this->actions as $a) {
            $opts[$a->handle()] = $a->label();
        }
        return $opts;
    }

    /**
     * Register the built-in rule action handlers. Resolves each action
     * from the container so dependencies (repositories, services, the
     * shared `DispatchOutboundWebhookAction`) are wired automatically.
     */
    public function registerDefaults(): void
    {
        $defaults = [
            \Goldnead\WebhookManager\Actions\SendOutboundWebhookAction::class,
            \Goldnead\WebhookManager\Actions\CreateEntryAction::class,
            \Goldnead\WebhookManager\Actions\UpdateEntryAction::class,
            \Goldnead\WebhookManager\Actions\CreateFormSubmissionAction::class,
            \Goldnead\WebhookManager\Actions\DispatchEventAction::class,
            \Goldnead\WebhookManager\Actions\SendEmailAction::class,
            \Goldnead\WebhookManager\Actions\SendSlackWebhookAction::class,
            \Goldnead\WebhookManager\Actions\SetFieldValueAction::class,
            \Goldnead\WebhookManager\Actions\WriteLogNoteAction::class,
        ];

        foreach ($defaults as $class) {
            $this->register(app($class));
        }
    }
}
