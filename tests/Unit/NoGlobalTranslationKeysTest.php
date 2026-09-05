<?php

namespace Goldnead\WebhookManager\Tests\Unit;

use Goldnead\WebhookManager\Services\FailureClassifier;
use Goldnead\WebhookManager\Tests\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/**
 * Every visible string this addon renders must be named inside its own
 * translation namespace, every name it uses must exist in both languages, and
 * every handle that reaches a runtime-built key must have an entry.
 *
 * Laravel's JSON translations are GLOBAL. `__('Delivery')` is not a key that
 * belongs to whoever wrote it — it belongs to whichever installed package
 * defines it last. Six sibling addons in this family register
 * `loadJsonTranslationsFrom`, and `statamic-marketing` defines
 * `"Delivery": "Versand"`. The consequence, seen in the playground on
 * 04.09.2026, was a delivery detail page titled **„Versand #266"** in German —
 * a word from another addon's vocabulary, on a screen this one owns.
 *
 * The same key that is merely hijackable today is also plainly untranslated:
 * a global key core does not itself define (`Method`, `Attempts`,
 * `Outbound Webhooks`) stays English in a German CP forever, because there is
 * no file in which an addon could translate it without claiming the word for
 * every other package too.
 *
 * The third failure has no global key in it at all and slipped past the first
 * version of this test: a key assembled at runtime out of a handle that no
 * language file knows. Laravel and Statamic's JS `__()` both hand THE KEY back
 * on a miss, so what reaches the screen is `webhook-man…`. That is how
 * `failure_types.unknown` printed itself into the insights panel. Static
 * scanning cannot resolve those keys, so the last two tests come at it from
 * the data side and check the handles instead.
 */
class NoGlobalTranslationKeysTest extends TestCase
{
    /** Where a visible string can come from. */
    private const SCANNED = ['resources/js', 'src', 'resources/views', 'config'];

    private const NAMESPACE_PREFIX = 'webhook-manager::';

    public function test_no_source_file_uses_a_global_translation_key(): void
    {
        $offenders = [];

        foreach ($this->translationCalls() as $call) {
            if (! str_starts_with($call['literal'], self::NAMESPACE_PREFIX)) {
                $offenders[] = "{$call['file']}:{$call['line']}: {$call['fn']}('{$call['literal']}')";
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['Global translation keys found. Every one of these is a word another'],
            ['installed addon may redefine, and one no addon can translate on its own.'],
            ['Move them to '.self::NAMESPACE_PREFIX.'messages.cp.<key>:'],
            $offenders,
        )));
    }

    /**
     * A key that resolves in one language and not the other is the same defect
     * with a longer fuse: the screen reads correctly for whoever added it and
     * prints the raw key for everyone else.
     */
    public function test_every_used_key_exists_in_german_and_english(): void
    {
        $de = require __DIR__.'/../../resources/lang/de/messages.php';
        $en = require __DIR__.'/../../resources/lang/en/messages.php';

        $missing = [];
        $prefix = self::NAMESPACE_PREFIX.'messages.';

        foreach ($this->translationCalls() as $call) {
            $literal = $call['literal'];

            if (! str_starts_with($literal, $prefix)) {
                continue; // another file of this namespace (nav, settings, …)
            }

            $path = substr($literal, strlen($prefix));

            // Assembled at runtime — PHP interpolation or a JS concatenation.
            // The two tests below cover those from the data side.
            if (str_contains($path, '{') || str_ends_with($path, '.')) {
                continue;
            }

            foreach (['de' => $de, 'en' => $en] as $lang => $table) {
                if ($this->dig($table, $path) === null) {
                    $missing[] = "$lang: $path  ({$call['file']}:{$call['line']})";
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)),
            "Translation keys used in code but absent from a language file:\n"
            .implode("\n", array_unique($missing)));
    }

    /**
     * Both listings and the insights panel build
     * `failure_types.<handle>` at runtime. Every handle the classifier can
     * produce needs an entry, plus the `unknown` bucket DeliveryStatsService
     * groups unclassified failures under — the one that was missing.
     */
    public function test_every_failure_type_has_a_label_in_both_languages(): void
    {
        $handles = array_values((new ReflectionClass(FailureClassifier::class))->getConstants());
        $handles[] = 'unknown';

        $this->assertHandlesAreTranslated('failure_types', $handles);
    }

    /**
     * The log listing builds `cp.log_types.<handle>` the same way, out of the
     * first argument of every SystemLogger call in the addon. Read from the
     * source so a newly logged event cannot quietly arrive without a label.
     */
    public function test_every_logged_event_type_has_a_label_in_both_languages(): void
    {
        $handles = [];

        foreach ($this->sourceFiles(['src']) as $file) {
            // `$this->error('network', …)` in HttpClient is a result kind, not
            // a log type — it is that class's own method.
            if (str_ends_with($file, 'Services/Http/HttpClient.php')) {
                continue;
            }

            preg_match_all("/(?:->|::)(?:info|warning|error|debug)\(\s*'([a-z_]+)'/", file_get_contents($file), $m);
            $handles = [...$handles, ...$m[1]];
        }

        $handles = array_values(array_unique($handles));

        $this->assertNotEmpty($handles, 'No SystemLogger calls found — the scan is broken, not the labels.');
        $this->assertHandlesAreTranslated('cp.log_types', $handles);
    }

    /**
     * @param  list<string>  $handles
     */
    private function assertHandlesAreTranslated(string $group, array $handles): void
    {
        $tables = [
            'de' => require __DIR__.'/../../resources/lang/de/messages.php',
            'en' => require __DIR__.'/../../resources/lang/en/messages.php',
        ];

        $missing = [];

        foreach ($handles as $handle) {
            foreach ($tables as $lang => $table) {
                if ($this->dig($table, "$group.$handle") === null) {
                    $missing[] = "$lang: $group.$handle";
                }
            }
        }

        $this->assertSame([], $missing,
            "Handles that reach a runtime-built key with no entry behind it.\n"
            ."Each of these prints the raw translation key on screen:\n"
            .implode("\n", $missing));
    }

    /**
     * Every translation call in the addon's own source, with its position.
     *
     * Covers both quote styles and both functions. The first version of this
     * test matched only `__('…')`: `__("…")` walked straight past it, and so
     * did all eleven `trans_choice()` calls, and `config/` was not scanned at
     * all.
     *
     * Occurrences inside comments are skipped on purpose: several files quote
     * the old global key in prose to record why it had to go, and a test that
     * forbade that would forbid the explanation along with the defect.
     *
     * @return list<array{file:string,line:int,fn:string,literal:string}>
     */
    private function translationCalls(): array
    {
        $root = realpath(__DIR__.'/../..');
        $calls = [];

        foreach ($this->sourceFiles(self::SCANNED) as $file) {
            $source = file_get_contents($file);
            $relative = str_replace($root.'/', '', $file);

            $pattern = '/\b(__|trans_choice)\(\s*'
                ."(?:'((?:[^'\\\\]|\\\\.)*)'"      // single-quoted
                .'|"((?:[^"\\\\]|\\\\.)*)")/';     // double-quoted

            preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

            foreach ($matches as $match) {
                $offset = $match[0][1];
                $lineStart = strrpos(substr($source, 0, $offset), "\n");
                $before = trim(substr($source, $lineStart + 1, $offset - $lineStart - 1));

                if (preg_match('/^(\*|\/\/|\/\*|<!--|#)/', $before)) {
                    continue;
                }

                $raw = ($match[2][0] ?? '') !== '' ? $match[2][0] : ($match[3][0] ?? '');

                $calls[] = [
                    'file' => $relative,
                    'line' => substr_count(substr($source, 0, $offset), "\n") + 1,
                    'fn' => $match[1][0],
                    'literal' => str_replace(["\\'", '\\"', '\\\\'], ["'", '"', '\\'], $raw),
                ];
            }
        }

        return $calls;
    }

    /**
     * @param  list<string>  $dirs
     * @return list<string>
     */
    private function sourceFiles(array $dirs): array
    {
        $root = realpath(__DIR__.'/../..');
        $files = [];

        foreach ($dirs as $dir) {
            if (! is_dir($root.'/'.$dir)) {
                continue;
            }

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir)) as $file) {
                if ($file->isFile() && preg_match('/\.(vue|php|js)$/', $file->getFilename())) {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }

    /** Resolve a dotted path in a nested translation array, or null. */
    private function dig(array $table, string $path): mixed
    {
        $node = $table;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                return null;
            }

            $node = $node[$segment];
        }

        return $node;
    }
}
