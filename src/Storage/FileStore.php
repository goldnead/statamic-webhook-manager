<?php

namespace Goldnead\WebhookManager\Storage;

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

/**
 * Low-level filesystem helper for the flat-file storage driver.
 *
 * Wraps all YAML I/O under a single root directory (typically
 * `content/webhooks/`). Uses symfony/yaml directly rather than Statamic
 * facades so the store has no dependency on a booted Statamic instance —
 * it works in unit tests and console contexts alike.
 */
class FileStore
{
    public function __construct(protected string $root)
    {
    }

    public function root(): string
    {
        return $this->root;
    }

    public function path(string $relative): string
    {
        return rtrim($this->root, '/').'/'.ltrim($relative, '/');
    }

    public function exists(string $relative): bool
    {
        return File::exists($this->path($relative));
    }

    /**
     * Parse a YAML file into an array. Missing/empty files yield [].
     *
     * @return array<string,mixed>
     */
    public function readYaml(string $relative): array
    {
        $path = $this->path($relative);
        if (! File::exists($path)) {
            return [];
        }

        $parsed = Yaml::parse((string) File::get($path));

        return is_array($parsed) ? $parsed : [];
    }

    /**
     * Write an array as YAML, creating parent directories as needed.
     *
     * @param  array<string,mixed>  $data
     */
    public function writeYaml(string $relative, array $data): void
    {
        $path = $this->path($relative);
        $dir = dirname($path);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, recursive: true);
        }

        $yaml = Yaml::dump(
            $data,
            inline: 6,
            indent: 2,
            flags: Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_NULL_AS_TILDE,
        );

        File::put($path, $yaml);
    }

    public function delete(string $relative): bool
    {
        $path = $this->path($relative);

        return File::exists($path) ? File::delete($path) : false;
    }

    /**
     * Relative paths of every file matching a glob under the root.
     *
     * @return array<int,string>
     */
    public function glob(string $pattern): array
    {
        $full = $this->path($pattern);
        $matches = File::glob($full) ?: [];

        $rootLen = strlen(rtrim($this->root, '/').'/');

        return array_values(array_map(
            fn (string $abs) => substr($abs, $rootLen),
            $matches,
        ));
    }
}
