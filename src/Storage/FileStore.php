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
    protected BrandSegments $segments;

    public function __construct(protected string $root, ?BrandSegments $segments = null)
    {
        $this->segments = $segments ?? new BrandSegments;
    }

    public function root(): string
    {
        return $this->root;
    }

    /**
     * The absolute path this context **writes** to.
     *
     * Reads must not use this: on a multi-brand install that has not run
     * `webhook-manager:migrate-flat-brands` yet, the definitions are still in
     * the pre-brand root while writes already go to the brand directory.
     */
    public function path(string $relative): string
    {
        return $this->pathIn($this->segments->write(), $relative);
    }

    /** The absolute path inside one segment; `''` is the pre-brand root. */
    public function pathIn(string $segment, string $relative): string
    {
        $base = rtrim($this->root, '/');
        $prefix = $segment === '' ? $base : $base.'/'.$segment;

        return $prefix.'/'.ltrim($relative, '/');
    }

    /**
     * The first existing path across the readable segments, or null.
     *
     * Null means "not there", and — with multi-brand on and no current brand —
     * also "you may not look".
     */
    public function resolve(string $relative): ?string
    {
        foreach ($this->segments->read() ?? [] as $segment) {
            $candidate = $this->pathIn($segment, $relative);

            if (File::exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Where a write lands: the current segment, unless the file already exists
     * in a readable one. Without this an install that has not migrated yet
     * would gain a second copy that shadows the original — one handle, two
     * files, and an edit that stops being visible to whatever reads the old one.
     */
    protected function writePath(string $relative): string
    {
        return $this->resolve($relative) ?? $this->path($relative);
    }

    public function exists(string $relative): bool
    {
        return $this->resolve($relative) !== null;
    }

    /**
     * Parse a YAML file into an array. Missing/empty files yield [].
     *
     * @return array<string,mixed>
     */
    public function readYaml(string $relative): array
    {
        $path = $this->resolve($relative);
        if ($path === null) {
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
        $path = $this->writePath($relative);
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
        // Delete where the file actually is, not where a new one would go.
        $path = $this->resolve($relative);

        return $path !== null ? File::delete($path) : false;
    }

    /**
     * Relative paths of every file matching a glob under the root.
     *
     * @return array<int,string>
     */
    public function glob(string $pattern): array
    {
        $found = [];

        foreach ($this->segments->read() ?? [] as $segment) {
            $prefixLen = strlen($this->pathIn($segment, ''));

            foreach (File::glob($this->pathIn($segment, $pattern)) ?: [] as $abs) {
                $relative = substr($abs, $prefixLen);

                // The first segment wins: a migrated file shadows a copy an
                // interrupted migration may have left in the old root.
                $found[$relative] ??= true;
            }
        }

        return array_keys($found);
    }
}
