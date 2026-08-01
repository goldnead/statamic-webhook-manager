<?php

namespace Goldnead\WebhookManager\Registries;

use Goldnead\WebhookManager\Contracts\PresetInterface;
use Goldnead\WebhookManager\Presets\DiscordPreset;
use Goldnead\WebhookManager\Presets\GenericJsonPreset;
use Goldnead\WebhookManager\Presets\MakePreset;
use Goldnead\WebhookManager\Presets\MicrosoftTeamsPreset;
use Goldnead\WebhookManager\Presets\N8nPreset;
use Goldnead\WebhookManager\Presets\SlackPreset;
use Goldnead\WebhookManager\Presets\ZapierPreset;

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
            SlackPreset::class,
            DiscordPreset::class,
            MicrosoftTeamsPreset::class,
            ZapierPreset::class,
            MakePreset::class,
            N8nPreset::class,
            GenericJsonPreset::class,
        ];

        foreach ($defaults as $class) {
            $this->register(app($class));
        }
    }
}
