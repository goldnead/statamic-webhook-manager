<?php

namespace Goldnead\WebhookManager\Registries;

use Goldnead\WebhookManager\Actions\CreateEntryAction;
use Goldnead\WebhookManager\Actions\CreateFormSubmissionAction;
use Goldnead\WebhookManager\Actions\DispatchEventAction;
use Goldnead\WebhookManager\Actions\SendEmailAction;
use Goldnead\WebhookManager\Actions\SendOutboundWebhookAction;
use Goldnead\WebhookManager\Actions\SendSlackWebhookAction;
use Goldnead\WebhookManager\Actions\SetFieldValueAction;
use Goldnead\WebhookManager\Actions\UpdateEntryAction;
use Goldnead\WebhookManager\Actions\WriteLogNoteAction;
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
            SendOutboundWebhookAction::class,
            CreateEntryAction::class,
            UpdateEntryAction::class,
            CreateFormSubmissionAction::class,
            DispatchEventAction::class,
            SendEmailAction::class,
            SendSlackWebhookAction::class,
            SetFieldValueAction::class,
            WriteLogNoteAction::class,
        ];

        foreach ($defaults as $class) {
            $this->register(app($class));
        }
    }
}
