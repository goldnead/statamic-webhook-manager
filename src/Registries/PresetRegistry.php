<?php

namespace Goldnead\WebhookManager\Registries;

use Goldnead\WebhookManager\Contracts\PresetInterface;

/**
 * Registry of outbound integration presets. Built-ins are registered in
 * WebhookManagerServiceProvider::bootRegistries(); third parties register
 * custom presets via WebhookManager::registerPreset(...).
 */
class PresetRegistry
{
    /** @var array<string, PresetInterface> */
    protected array $presets = [];

    public function register(PresetInterface $preset): void
    {
        $this->presets[$preset->handle()] = $preset;
    }

    public function get(string $handle): ?PresetInterface
    {
        return $this->presets[$handle] ?? null;
    }

    /** @return array<string, PresetInterface> */
    public function all(): array
    {
        return $this->presets;
    }

    /**
     * Gallery payload for the CP — one entry per preset, grouped-ready.
     *
     * @return array<int, array<string, string>>
     */
    public function gallery(): array
    {
        $out = [];
        foreach ($this->presets as $preset) {
            $out[] = [
                'handle' => $preset->handle(),
                'label' => $preset->label(),
                'icon' => $preset->icon(),
                'category' => $preset->category(),
                'description' => $preset->description(),
            ];
        }

        return $out;
    }

    public function registerDefaults(): void
    {
        $defaults = [
            \Goldnead\WebhookManager\Presets\SlackPreset::class,
            \Goldnead\WebhookManager\Presets\DiscordPreset::class,
            \Goldnead\WebhookManager\Presets\MicrosoftTeamsPreset::class,
            \Goldnead\WebhookManager\Presets\ZapierPreset::class,
            \Goldnead\WebhookManager\Presets\MakePreset::class,
            \Goldnead\WebhookManager\Presets\N8nPreset::class,
            \Goldnead\WebhookManager\Presets\GenericJsonPreset::class,
        ];

        foreach ($defaults as $class) {
            $this->register(app($class));
        }
    }
}
