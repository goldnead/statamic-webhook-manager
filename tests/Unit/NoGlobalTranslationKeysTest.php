<?php

namespace Goldnead\WebhookManager\Tests\Unit;

use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
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
 * scanning cannot resolve those keys, so the tests below come at it from the
 * data side and check the handles instead.
 *
 * Every place in the addon that builds a key at runtime, and what checks it —
 * a map worth keeping current, because an unlisted one is a blind spot:
 *
 *   failure_types.*        FailureClassifier constants + `unknown`   here
 *   cp.log_types.*         every SystemLogger call in src/           here
 *   cp.condition_ops.*     the OPS list in ConditionRow.vue          here
 *   cp.delivery_status.*   Delivery::STATUS_* constants              here
 *   insights.status.*      the same constants                        here
 *   subject_types.*        config `subjects` + the built-in triggers here
 *   settings.fields.*      Settings::groups(), both locales          SettingsEditorTest
 *   settings.options.*     the same                                  SettingsEditorTest
 *
 * The remaining one, `insights.no_<dimension>`, has a single call site passing
 * a literal, so the scan above already sees it.
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
     * A translation call whose argument is a VARIABLE is invisible to the scan
     * above: it sees `__($key)` and cannot know what `$key` holds. That blind
     * spot hid twelve English operator labels in the condition editor for
     * months — `ConditionRow.vue` kept them in a list, passed each through
     * `__()`, and since a global key resolves to itself, the English source
     * string arrived on screen looking like a deliberate translation.
     *
     * So the rule is not "variable keys are exempt" but "a variable key must
     * demonstrably be built inside this addon's namespace". Every legitimate
     * one in the codebase assembles its key from a
     * `webhook-manager::…` literal a line or two above the call; the broken one
     * had no such literal anywhere near it. That is what this checks — and the
     * VALUES behind those keys are then checked by the data-driven tests below,
     * which is the only thing that can reach them.
     */
    public function test_no_translation_call_takes_a_key_assembled_outside_the_namespace(): void
    {
        $offenders = [];

        foreach ($this->sourceFiles(self::SCANNED) as $file) {
            $source = file_get_contents($file);
            $relative = str_replace(realpath(__DIR__.'/../..').'/', '', $file);

            // A call whose first argument does not open with a quote.
            preg_match_all('/\b(__|trans_choice)\(\s*(?![\'"])([^\s,);]+)/', $source, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

            foreach ($matches as $match) {
                $offset = $match[0][1];
                $lineStart = strrpos(substr($source, 0, $offset), "\n");
                $line = substr_count(substr($source, 0, $offset), "\n") + 1;
                $before = trim(substr($source, $lineStart + 1, $offset - $lineStart - 1));

                if (preg_match('/^(\*|\/\/|\/\*|<!--|#)/', $before)) {
                    continue;
                }

                // The five lines above the call are where the key gets built.
                $context = implode("\n", array_slice(explode("\n", $source), max(0, $line - 6), 6));

                if (str_contains($context, self::NAMESPACE_PREFIX)) {
                    continue;
                }

                $offenders[] = "{$relative}:{$line}: {$match[1][0]}({$match[2][0]}) — no ".self::NAMESPACE_PREFIX.' literal builds this key';
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['A translation key assembled from something other than this addon\'s'],
            ['namespace. `__()` returns the key when it cannot resolve one, so an'],
            ['English handle passed in here reaches the screen as its own'],
            ['"translation" and reads as intentional:'],
            $offenders,
        )));
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
     * The condition editor builds `cp.condition_ops.<handle>` from its own OPS
     * list. Read that list out of the component, so an operator added there
     * cannot arrive without wording — which is exactly how all twelve of them
     * stood on screen in English.
     */
    public function test_every_condition_operator_has_a_label_in_both_languages(): void
    {
        $source = file_get_contents(__DIR__.'/../../resources/js/components/rules/ConditionRow.vue');

        $this->assertSame(1, preg_match('/const OPS = \[(.*?)\];/s', $source, $m),
            'The OPS list in ConditionRow.vue could not be read — this test is measuring nothing.');

        preg_match_all("/'([a-z_]+)'/", $m[1], $handles);

        $this->assertNotEmpty($handles[1]);
        $this->assertHandlesAreTranslated('cp.condition_ops', $handles[1]);
    }

    /**
     * The remaining runtime-built keys, each read from the source of truth for
     * its handles rather than from a list repeated here:
     *
     *  - `cp.delivery_status.*` — Deliveries/Show.vue and PresentsDeliveryStatuses
     *  - `insights.status.*`    — WebhookMetric, for the sibling analytics addon
     *  - `messages.subject_types.*` — DeliveryController, for the types this
     *    addon ships defaults for. A type contributed by another package
     *    legitimately has no entry and falls back to `ucfirst()`, so only the
     *    configured ones are required.
     */
    public function test_every_delivery_status_has_a_label_in_both_languages(): void
    {
        $handles = array_values(array_filter(
            (new ReflectionClass(Delivery::class))->getConstants(),
            fn (string $name) => str_starts_with($name, 'STATUS_'),
            ARRAY_FILTER_USE_KEY,
        ));

        $this->assertNotEmpty($handles);
        $this->assertHandlesAreTranslated('cp.delivery_status', $handles);

        $insights = require __DIR__.'/../../resources/lang/de/insights.php';
        $insightsEn = require __DIR__.'/../../resources/lang/en/insights.php';

        foreach ($handles as $handle) {
            $this->assertArrayHasKey($handle, $insights['status'], "de: insights.status.$handle");
            $this->assertArrayHasKey($handle, $insightsEn['status'], "en: insights.status.$handle");
        }
    }

    public function test_every_configured_subject_type_has_a_label_in_both_languages(): void
    {
        $config = require __DIR__.'/../../config/webhook-manager.php';
        $configured = array_map('strval', array_keys((array) ($config['subjects'] ?? [])));

        // The four built-in triggers resolve a subject with no configuration.
        $handles = array_values(array_unique([...$configured, 'entry', 'user', 'asset', 'form_submission']));

        $this->assertHandlesAreTranslated('subject_types', $handles);
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
