<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Tests\TestCase;

/**
 * The Response panel of the delivery detail view died on SUCCESSFUL
 * deliveries — and only on those.
 *
 * `contentTypeMode()` did:
 *
 *     (headers?.['content-type'] ?? '').toLowerCase()
 *
 * PSR-7 / Guzzle response headers are `{"content-type": ["application/json"]}`
 * — an ARRAY. `.toLowerCase()` is not a function on an array, the TypeError
 * took the whole panel with it (status code, duration, headers, body), and a
 * FAILED delivery rendered fine because it has no response headers at all.
 * The panel was therefore missing exactly where someone looks for it.
 *
 * There is no JS test runner in this package, so this is verified two ways:
 * structurally (always), and behaviourally by executing the real functions
 * under node when node is available.
 */
class DeliveryShowHandlesArrayHeadersTest extends TestCase
{
    private function source(): string
    {
        $path = __DIR__.'/../../resources/js/pages/deliveries/Show.vue';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_the_content_type_lookup_does_not_call_string_methods_on_a_raw_header(): void
    {
        $source = $this->source();

        $this->assertDoesNotMatchRegularExpression(
            '/headers\??\.?\[[\'"]content-type[\'"]\][^;]*\.toLowerCase\(\)/i',
            $source,
            'contentTypeMode() reads the raw header and calls a string method on it; '
            .'PSR-7 headers are arrays and this throws a TypeError that blanks the Response panel.'
        );

        $this->assertStringContainsString(
            'Array.isArray',
            $source,
            'The header lookup must flatten array-valued headers before treating them as a string.'
        );
    }

    /**
     * The controller has always computed `curl`, `correlation_id`,
     * `trigger_type` and `attempts` — the template printed none of them, and
     * `attempts` was hidden behind a `v-if` that only rendered on failures.
     * The payload assertions live in DeliveryDetailAndReplayTest; this locks
     * the fields actually being rendered.
     */
    public function test_the_detail_template_renders_correlation_trigger_attempts_and_curl(): void
    {
        $source = $this->source();

        foreach ([
            'delivery.correlation_id' => 'the correlation ID (only ever visible inside the raw header JSON)',
            'delivery.curl' => 'the pre-computed cURL command (never printed at all)',
            'delivery.trigger_label' => 'the trigger as a named field',
        ] as $needle => $what) {
            $this->assertStringContainsString(
                $needle,
                $source,
                "Delivery detail view does not render {$what}."
            );
        }

        // Attempts must render unconditionally, not only inside the
        // error-only "Timing & Errors" panel.
        $this->assertMatchesRegularExpression(
            '/\{\{\s*delivery\.attempts\s*\?\?\s*[\'"]—[\'"]\s*\}\}/',
            $source,
            'The attempt counter still only shows up on failed deliveries.'
        );
    }

    public function test_the_extracted_helpers_survive_array_valued_headers(): void
    {
        $node = trim((string) shell_exec('command -v node 2>/dev/null'));
        if ($node === '') {
            $this->markTestSkipped('node is not available; the structural guard above still applies.');
        }

        $source = $this->source();

        // Pull the two functions straight out of the SFC so the test runs the
        // shipped implementation, not a copy of it.
        preg_match('/function headerValue\(.*?\n\}/s', $source, $headerValue);
        preg_match('/function contentTypeMode\(.*?\n\}/s', $source, $contentTypeMode);

        $this->assertNotEmpty($headerValue, 'headerValue() not found in Show.vue');
        $this->assertNotEmpty($contentTypeMode, 'contentTypeMode() not found in Show.vue');

        $script = <<<JS
{$headerValue[0]}
{$contentTypeMode[0]}

const assert = (cond, msg) => { if (!cond) { console.log('FAIL: ' + msg); process.exit(1); } };

// PSR-7 shape — this is what the panel actually receives on a success.
assert(contentTypeMode({'content-type': ['application/json']}) === 'json', 'array-valued content-type');
// Canonical casing, still an array.
assert(contentTypeMode({'Content-Type': ['text/html; charset=UTF-8']}) === 'html', 'array-valued Content-Type');
// Plain string still works.
assert(contentTypeMode({'content-type': 'application/xml'}) === 'xml', 'string content-type');
// Failed deliveries: no headers at all.
assert(contentTypeMode({}) === 'text', 'empty headers');
assert(contentTypeMode(null) === 'text', 'null headers');
assert(contentTypeMode(undefined) === 'text', 'undefined headers');
console.log('OK');
JS;

        $file = tempnam(sys_get_temp_dir(), 'whm-show-').'.mjs';
        file_put_contents($file, $script);

        $output = (string) shell_exec(escapeshellcmd($node).' '.escapeshellarg($file).' 2>&1');
        @unlink($file);

        $this->assertStringContainsString('OK', $output, "Header handling failed under node:\n".$output);
    }
}
