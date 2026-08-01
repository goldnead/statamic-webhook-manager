<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Tests\TestCase;

/**
 * Every locale the addon ships must cover every key `en` has.
 *
 * A missing key silently falls back to English, so a partial translation does
 * not fail — it produces a Control Panel that changes language mid-screen.
 * `de/` used to be eight keys out of 103 with no `nav.php` and no
 * `permissions.php` at all, which meant the whole sidebar and all eleven
 * permission labels stayed English in a German CP.
 *
 * The rule this enforces is: ship a locale completely, or do not ship it.
 */
class LangFilesAreCompleteTest extends TestCase
{
    public function test_every_shipped_locale_covers_every_english_key(): void
    {
        $reference = $this->flatten($this->load('en'));

        $this->assertNotEmpty($reference, 'The English lang files could not be read.');

        foreach ($this->locales() as $locale) {
            if ($locale === 'en') {
                continue;
            }

            $translated = $this->flatten($this->load($locale));
            $missing = array_values(array_diff(array_keys($reference), array_keys($translated)));

            $this->assertSame([], $missing, "Locale '{$locale}' is missing ".count($missing).' key(s): '.implode(', ', array_slice($missing, 0, 20)));
        }
    }

    public function test_no_locale_carries_keys_english_does_not_have(): void
    {
        // A stale key is dead weight and usually the fossil of a renamed one.
        $reference = $this->flatten($this->load('en'));

        foreach ($this->locales() as $locale) {
            if ($locale === 'en') {
                continue;
            }

            $extra = array_values(array_diff(
                array_keys($this->flatten($this->load($locale))),
                array_keys($reference),
            ));

            $this->assertSame([], $extra, "Locale '{$locale}' has keys English does not: ".implode(', ', $extra));
        }
    }

    public function test_placeholders_survive_translation(): void
    {
        // ":count records pruned" translated without :count is a string that
        // renders a sentence with a hole in it.
        $reference = $this->flatten($this->load('en'));

        foreach ($this->locales() as $locale) {
            if ($locale === 'en') {
                continue;
            }

            foreach ($this->flatten($this->load($locale)) as $key => $value) {
                if (! isset($reference[$key]) || ! is_string($reference[$key]) || ! is_string($value)) {
                    continue;
                }

                preg_match_all('/:([a-z_]+)/', $reference[$key], $expected);

                foreach (array_unique($expected[1]) as $placeholder) {
                    $this->assertStringContainsString(
                        ':'.$placeholder,
                        $value,
                        "{$locale}.{$key} drops the :{$placeholder} placeholder."
                    );
                }
            }
        }
    }

    /** @return array<int, string> */
    protected function locales(): array
    {
        return array_values(array_filter(
            array_map('basename', glob($this->langPath().'/*', GLOB_ONLYDIR) ?: []),
        ));
    }

    /** @return array<string, mixed> */
    protected function load(string $locale): array
    {
        $out = [];

        foreach (glob($this->langPath()."/{$locale}/*.php") ?: [] as $file) {
            $out[basename($file, '.php')] = require $file;
        }

        return $out;
    }

    /** @return array<string, mixed> */
    protected function flatten(array $data, string $prefix = ''): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $out += $this->flatten($value, $path);

                continue;
            }

            $out[$path] = $value;
        }

        return $out;
    }

    protected function langPath(): string
    {
        return realpath(__DIR__.'/../../resources/lang');
    }
}
