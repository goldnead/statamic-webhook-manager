<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Tests\TestCase;
use Statamic\Facades\Permission;

/**
 * Every ability string the addon asks about must be an ability the addon
 * registered.
 *
 * Statamic answers `false` for an ability nobody registered — for every user,
 * super users included. So a one-character typo does not fail, it hides a
 * feature, and it hides it silently: no exception, no log line, no failing
 * test. v1.9.0 shipped exactly that twice (`test outbound webhooks` before it
 * was registered, then `manage rules` on the Overview screen, where it hid the
 * "Add a Rule" CTA on the first screen a new user sees).
 *
 * This test is deliberately structural rather than per-screen: it reads every
 * authorization call site out of src/ and compares the literal ability strings
 * against the registry. A new controller with a new typo fails here on the
 * commit that introduces it, without anyone remembering to add a case.
 */
class PermissionStringsAreRegisteredTest extends TestCase
{
    public function test_every_ability_the_code_asks_about_is_registered(): void
    {
        $registered = $this->registeredAbilities();

        $this->assertNotEmpty($registered, 'No permissions were registered — the scan below would pass vacuously.');

        $used = $this->abilitiesUsedInSource();

        $this->assertNotEmpty($used, 'No authorization call sites were found — the regexes below have gone stale.');

        $unregistered = [];

        foreach ($used as $ability => $sites) {
            if (! in_array($ability, $registered, true)) {
                $unregistered[$ability] = $sites;
            }
        }

        $this->assertSame([], $unregistered, $this->describe($unregistered, $registered));
    }

    public function test_every_registered_ability_is_actually_checked_somewhere(): void
    {
        // The mirror image: an ability that appears in the roles UI but is
        // never consulted is a promise to the operator that nothing keeps.
        $used = array_keys($this->abilitiesUsedInSource());
        $unused = array_values(array_diff($this->registeredAbilities(), $used));

        $this->assertSame([], $unused, 'Registered but never checked: '.implode(', ', $unused));
    }

    /** @return array<int, string> */
    protected function registeredAbilities(): array
    {
        // Scoped to this addon's own group so core's permissions neither pad
        // the "registered" set nor trip the never-checked assertion below.
        return Permission::all()
            ->filter(fn ($permission) => $permission->group() === 'webhook_manager')
            ->map(fn ($permission) => $permission->value())
            ->filter(fn ($value) => is_string($value))
            ->values()
            ->all();
    }

    /**
     * Literal ability strings passed to any authorization call in src/.
     *
     * @return array<string, array<int, string>> ability => ["file:line", …]
     */
    protected function abilitiesUsedInSource(): array
    {
        $found = [];

        foreach ($this->sourceFiles() as $file) {
            $relative = 'src/'.ltrim(str_replace($this->srcPath(), '', $file), '/');
            $lines = file($file, FILE_IGNORE_NEW_LINES);

            foreach ($lines as $i => $line) {
                foreach ($this->abilitiesOnLine($line) as $ability) {
                    $found[$ability][] = $relative.':'.($i + 1);
                }
            }
        }

        // Registration sites are not usage sites.
        unset($found['__registration__']);

        return $found;
    }

    /** @return array<int, string> */
    protected function abilitiesOnLine(string $line): array
    {
        // Permission::register('x') is the definition, not a check.
        if (preg_match('/Permission::register\(/', $line)) {
            return [];
        }

        $abilities = [];

        // ->can('x') / ->cannot('x') / $this->authorize('x') / authorizeOr403($r, 'x')
        $single = "/(?:->(?:can|cannot)|->authorize|authorizeOr403)\(\s*(?:\\\$[a-zA-Z_]+\s*,\s*)?'([^']+)'/";
        if (preg_match_all($single, $line, $m)) {
            $abilities = array_merge($abilities, $m[1]);
        }

        // authorizeAny($request, 'a', 'b', 'c') — variadic, so grab them all.
        if (preg_match("/authorizeAny\(\s*\\\$[a-zA-Z_]+\s*,\s*(.+)\)/", $line, $m)) {
            if (preg_match_all("/'([^']+)'/", $m[1], $inner)) {
                $abilities = array_merge($abilities, $inner[1]);
            }
        }

        // Only ability-shaped strings: multi-word, lowercase, no namespace
        // separators. Keeps `can('save')`-style Laravel gates on unrelated
        // objects and `->authorize($user, $item)` out of the set.
        return array_values(array_filter(
            array_unique($abilities),
            fn (string $s) => (bool) preg_match('/^[a-z]+(?: [a-z]+)+$/', $s),
        ));
    }

    /** @return array<int, string> */
    protected function sourceFiles(): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->srcPath(), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    protected function srcPath(): string
    {
        return realpath(__DIR__.'/../../src');
    }

    /**
     * @param  array<string, array<int, string>>  $unregistered
     * @param  array<int, string>  $registered
     */
    protected function describe(array $unregistered, array $registered): string
    {
        if ($unregistered === []) {
            return '';
        }

        $lines = ['Unregistered abilities are checked in src/. Each answers false for everyone, super users included:'];

        foreach ($unregistered as $ability => $sites) {
            $lines[] = sprintf('  "%s" at %s', $ability, implode(', ', $sites));
        }

        $lines[] = 'Registered abilities are: '.implode(', ', $registered);

        return implode("\n", $lines);
    }
}
