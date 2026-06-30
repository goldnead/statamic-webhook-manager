<?php

namespace Goldnead\WebhookManager\Storage;

use Illuminate\Support\Facades\File;

/**
 * Resolves and persists the active storage driver.
 *
 * The driver can be set two ways:
 *   - In config/env (`webhook-manager.storage.driver`) — the deploy default.
 *   - From the Control Panel — persisted to a small JSON file under the
 *     app's storage directory, which then takes precedence so an operator
 *     can switch storage without shell/.env access.
 *
 * The persisted file lives outside git (storage/), matching the
 * environment-specific nature of the choice — just like an env var.
 */
class StorageDriverManager
{
    public const SOURCE_CONTROL_PANEL = 'control_panel';

    public const SOURCE_CONFIG = 'config';

    /** The currently active driver. */
    public function current(): string
    {
        $persisted = $this->persisted();
        if ($persisted !== null) {
            return $persisted;
        }

        $configured = (string) config('webhook-manager.storage.driver', 'eloquent');

        return $this->valid($configured) ? $configured : 'eloquent';
    }

    /** Where the active driver value comes from. */
    public function source(): string
    {
        return $this->persisted() !== null ? self::SOURCE_CONTROL_PANEL : self::SOURCE_CONFIG;
    }

    /** Persist the chosen driver as the active one. */
    public function setDriver(string $driver): void
    {
        if (! $this->valid($driver)) {
            throw new \InvalidArgumentException("Unknown storage driver [{$driver}].");
        }

        $path = $this->settingsPath();
        $dir = dirname($path);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, recursive: true);
        }

        File::put($path, json_encode(['driver' => $driver], JSON_PRETTY_PRINT));
    }

    /** Drop the Control Panel override, falling back to config/env. */
    public function clearOverride(): void
    {
        if (File::exists($this->settingsPath())) {
            File::delete($this->settingsPath());
        }
    }

    public function settingsPath(): string
    {
        return storage_path('webhook-manager/storage.json');
    }

    /** The persisted driver, or null when no valid override is set. */
    protected function persisted(): ?string
    {
        $path = $this->settingsPath();
        if (! File::exists($path)) {
            return null;
        }

        $data = json_decode((string) File::get($path), true);
        $driver = is_array($data) ? ($data['driver'] ?? null) : null;

        return $this->valid($driver) ? $driver : null;
    }

    protected function valid(mixed $driver): bool
    {
        return is_string($driver) && in_array($driver, StorageMigrator::DRIVERS, true);
    }
}
