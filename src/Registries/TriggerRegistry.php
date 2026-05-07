<?php

namespace Goldnead\WebhookManager\Registries;

use Goldnead\WebhookManager\Contracts\TriggerInterface;
use Goldnead\WebhookManager\Triggers\AssetSavedTrigger;
use Goldnead\WebhookManager\Triggers\EntryDeletedTrigger;
use Goldnead\WebhookManager\Triggers\EntryPublishedTrigger;
use Goldnead\WebhookManager\Triggers\EntrySavedTrigger;
use Goldnead\WebhookManager\Triggers\EntryUnpublishedTrigger;
use Goldnead\WebhookManager\Triggers\FormSubmittedTrigger;
use Goldnead\WebhookManager\Triggers\UserSavedTrigger;

/**
 * Central registry of internal triggers.
 *
 * Listeners normalise framework events to a TriggerEvent via the trigger
 * implementation registered here. Third-party packages can register
 * additional triggers through this registry.
 */
class TriggerRegistry
{
    /** @var array<string, TriggerInterface> */
    protected array $triggers = [];

    public function register(TriggerInterface $trigger): void
    {
        $this->triggers[$trigger->handle()] = $trigger;
    }

    public function get(string $handle): ?TriggerInterface
    {
        return $this->triggers[$handle] ?? null;
    }

    public function has(string $handle): bool
    {
        return isset($this->triggers[$handle]);
    }

    /** @return array<string, TriggerInterface> */
    public function all(): array
    {
        return $this->triggers;
    }

    /**
     * For CP <select> options. Returns ["handle" => "label"].
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $opts = [];
        foreach ($this->triggers as $t) {
            $opts[$t->handle()] = $t->label();
        }
        ksort($opts);
        return $opts;
    }

    public function registerDefaults(): void
    {
        $this->register(new EntrySavedTrigger());
        $this->register(new EntryPublishedTrigger());
        $this->register(new EntryUnpublishedTrigger());
        $this->register(new EntryDeletedTrigger());
        $this->register(new FormSubmittedTrigger());
        $this->register(new UserSavedTrigger());
        $this->register(new AssetSavedTrigger());
    }
}
