<?php

namespace Goldnead\WebhookManager\Tests\Unit;

use Goldnead\WebhookManager\Tests\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Every visible string this addon renders must be named inside its own
 * translation namespace, and every name it uses must exist in both languages.
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
 * So the rule is absolute rather than case-by-case: no global `__()` key
 * anywhere in the addon. This test enforces it over the whole source tree, so
 * it holds for the next screen as well as the ones fixed on 05.09.2026.
 */
class NoGlobalTranslationKeysTest extends TestCase
{
    /** Where a visible string can come from. */
    private const SCANNED = ['resources/js', 'src', 'resources/views'];

    private const NAMESPACE_PREFIX = 'webhook-manager::';

    public function test_no_source_file_uses_a_global_translation_key(): void
    {
        $offenders = [];

        foreach ($this->translationCalls() as [$file, $literal]) {
            if (! str_starts_with($literal, self::NAMESPACE_PREFIX)) {
                $offenders[] = $file.': __(\''.$literal.'\')';
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['Global __() keys found. Every one of these is a word another installed'],
            ['addon may redefine, and one no addon can translate on its own.'],
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

        foreach ($this->translationCalls() as [$file, $literal]) {
            $prefix = self::NAMESPACE_PREFIX.'messages.';

            if (! str_starts_with($literal, $prefix)) {
                continue; // another file of this namespace (nav, settings, …)
            }

            $path = substr($literal, strlen($prefix));

            // Keys assembled at runtime (`…failure_types.` . $type) cannot be
            // resolved statically; their fallbacks are covered by the
            // Presents*-traits' own tests.
            if (str_ends_with($path, '.')) {
                continue;
            }

            foreach (['de' => $de, 'en' => $en] as $lang => $table) {
                if ($this->dig($table, $path) === null) {
                    $missing[] = "$lang: $path  ($file)";
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)),
            "Translation keys used in code but absent from a language file:\n"
            .implode("\n", array_unique($missing)));
    }

    /**
     * Every `__('…')` in the addon's own source, as [relative file, literal].
     *
     * Occurrences inside comments are skipped on purpose: several files quote
     * the old global key in prose to record why it had to go, and a test that
     * forbade that would forbid the explanation along with the defect.
     *
     * @return list<array{0:string,1:string}>
     */
    private function translationCalls(): array
    {
        $root = realpath(__DIR__.'/../..');
        $calls = [];

        foreach (self::SCANNED as $dir) {
            if (! is_dir($root.'/'.$dir)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir));

            foreach ($files as $file) {
                if (! $file->isFile() || ! preg_match('/\.(vue|php|js)$/', $file->getFilename())) {
                    continue;
                }

                $source = file_get_contents($file->getPathname());
                $relative = str_replace($root.'/', '', $file->getPathname());

                preg_match_all("/__\(\s*'((?:[^'\\\\]|\\\\.)*)'/", $source, $matches, PREG_OFFSET_CAPTURE);

                foreach ($matches[1] as $index => [$raw, $_]) {
                    $offset = $matches[0][$index][1];
                    $lineStart = strrpos(substr($source, 0, $offset), "\n");
                    $before = trim(substr($source, $lineStart + 1, $offset - $lineStart - 1));

                    if (preg_match('/^(\*|\/\/|\/\*|<!--|#)/', $before)) {
                        continue;
                    }

                    $calls[] = [$relative, str_replace(["\\'", '\\\\'], ["'", '\\'], $raw)];
                }
            }
        }

        return $calls;
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
