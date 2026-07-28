<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Tests\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Structural guard for the failure this control panel family keeps repeating:
 * the server rejects an input, nothing is written, and the screen does not
 * change — Save looks like a dead button.
 *
 * This is the cheap half of the guard. It reads the sources and can only see
 * that a page mentions an error somewhere; whether the element carrying it
 * actually renders is asserted one layer up, in
 * tests/js/cp-validation-visibility.test.js, because a source scan cannot see
 * a field hidden behind a `v-if` that is false in exactly the state where the
 * error occurs. Both layers are needed: this one fails the moment somebody
 * adds a form that submits without handling the rejection, which the mount
 * test would not notice because it only knows the pages it lists.
 *
 * Same shape as marketing v1.5.3's CpValidationVisibilityTest, extended for
 * the two submission styles this addon uses: Inertia's `router.*` and
 * `useForm().submit()`.
 */
class CpValidationVisibilityTest extends TestCase
{
    /** Marker attribute on the collected error output of a page. */
    private const SUMMARY_MARKER = 'data-webhook-form-errors';

    /** Every .vue page keyed by its path relative to resources/js/pages. */
    private function pages(): array
    {
        $pages = [];
        $root = dirname(__DIR__, 2).'/resources/js/pages';

        foreach (Finder::create()->files()->in($root)->name('*.vue') as $file) {
            $pages[$file->getRelativePathname()] = $this->withoutComments($file->getContents());
        }

        ksort($pages);

        return $pages;
    }

    /**
     * Strip comments before scanning.
     *
     * Without this, a page that explains in prose why it does *not* use
     * `router.post()` (deliveries/Show does exactly that) is counted as if it
     * did — and the guard demands an error output for a submission that is
     * not there. A comment is documentation, not behaviour.
     */
    private function withoutComments(string $source): string
    {
        $source = preg_replace('#/\*.*?\*/#s', '', $source);
        $source = preg_replace('#<!--.*?-->#s', '', $source);

        return preg_replace('#^\s*//.*$#m', '', $source);
    }

    /**
     * Top-level `function name() { … }` bodies of a `<script setup>` block,
     * keyed by name. Brace-matched rather than regexed, so nested objects and
     * closures stay inside the body they belong to.
     */
    private function functionBodies(string $source): array
    {
        $bodies = [];
        $offset = 0;

        while (preg_match('/\bfunction\s+(\w+)\s*\([^)]*\)\s*\{/', $source, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $name = $m[1][0];
            $start = $m[0][1] + strlen($m[0][0]);
            $depth = 1;
            $i = $start;

            while ($i < strlen($source) && $depth > 0) {
                if ($source[$i] === '{') {
                    $depth++;
                } elseif ($source[$i] === '}') {
                    $depth--;
                }

                $i++;
            }

            $bodies[$name] = substr($source, $start, $i - $start - 1);
            $offset = $i;
        }

        return $bodies;
    }

    /**
     * Does this block send something the server can reject?
     *
     * Two styles live in this addon: Inertia's bare router for actions on a
     * row, and `useForm().submit(verb, url)` for the edit masks. Only the
     * router style needs its own error branch — useForm fills `form.errors`
     * itself, which is what the masks render.
     */
    private function submitsViaRouter(string $block): bool
    {
        return (bool) preg_match('/router\.(post|patch|put|delete)\s*\(/', $block);
    }

    private function submitsViaForm(string $block): bool
    {
        return (bool) preg_match('/\w+\.submit\s*\(\s*[\'"]?(post|patch|put|delete)/', $block);
    }

    public function test_every_router_submission_handles_the_rejection_it_can_receive(): void
    {
        $missing = [];

        foreach ($this->pages() as $page => $source) {
            foreach ($this->functionBodies($source) as $name => $body) {
                if ($this->submitsViaRouter($body) && ! str_contains($body, 'onError')) {
                    $missing[] = "{$page}::{$name}()";
                }
            }
        }

        $this->assertSame([], $missing, 'These submit to the server but ignore a rejected response, so the failure is invisible: '.implode(', ', $missing));
    }

    public function test_no_page_submits_from_an_inline_handler_where_no_error_branch_can_live(): void
    {
        // `@click="router.delete(url, { … })"` in the template cannot grow an
        // onError without turning into a function first, and both places that
        // did this (templates listing, deliveries listing) had none.
        $inline = [];

        foreach ($this->pages() as $page => $source) {
            if (! preg_match('/<template>(.*)<\/template>/s', $source, $m)) {
                continue;
            }

            if (preg_match('/router\.(post|patch|put|delete)\s*\(/', $m[1])) {
                $inline[] = $page;
            }
        }

        $this->assertSame([], $inline, 'These submit straight from the markup, where a rejection has nowhere to be handled: '.implode(', ', $inline));
    }

    public function test_every_submitting_page_renders_a_collected_output_for_errors_that_belong_to_no_field(): void
    {
        $missing = [];

        foreach ($this->pages() as $page => $source) {
            $submits = $this->submitsViaRouter($source) || $this->submitsViaForm($source);

            if (! $submits) {
                continue;
            }

            if (! str_contains($source, self::SUMMARY_MARKER)) {
                $missing[] = $page;
            }
        }

        $this->assertSame([], $missing, 'These submit but have no place to show an error that maps to no field (a refused delete, a refused driver switch): '.implode(', ', $missing));
    }

    public function test_the_collected_output_uses_a_variant_the_alert_component_knows(): void
    {
        // Alert's variants are default | warning | error | success. Anything
        // else lands in $attrs and the banner renders in the neutral style —
        // present, but not looking like the error it reports. This addon
        // shipped both `variant="danger"` and `type="error"`.
        $wrong = [];

        foreach ($this->pages() as $page => $source) {
            if (! str_contains($source, self::SUMMARY_MARKER)) {
                continue;
            }

            foreach (explode('<Alert', $source) as $block) {
                if (! str_contains($block, self::SUMMARY_MARKER)) {
                    continue;
                }

                $head = substr($block, 0, strpos($block, '>') ?: strlen($block));

                if (! str_contains($head, 'variant="error"')) {
                    $wrong[] = $page;
                }
            }
        }

        $this->assertSame([], $wrong, 'The collected error output on these pages is not styled as an error: '.implode(', ', $wrong));
    }

    public function test_every_key_the_cp_validates_has_somewhere_to_show_its_error(): void
    {
        // Which page owns which FormRequest. A key is satisfied by a field
        // binding on that page, or by that page's collected output.
        $owners = [
            'SaveInboundEndpointRequest' => 'inbound/Edit.vue',
            'SaveOutboundWebhookRequest' => 'outbound/Edit.vue',
            'SaveRuleRequest' => 'rules/Edit.vue',
            'SaveTemplateRequest' => 'templates/Edit.vue',
        ];

        $pages = $this->pages();
        $unrendered = [];

        foreach ($owners as $request => $page) {
            $this->assertArrayHasKey($page, $pages, "{$page} no longer exists but {$request} still points at it.");

            $rules = file_get_contents(dirname(__DIR__, 2)."/src/Http/Requests/{$request}.php");
            preg_match_all("/^\s+'([a-z_][a-z_.*]*)'\s*=>/m", $rules, $keys);

            $this->assertNotEmpty($keys[1], "{$request} validates nothing — the rule parser has drifted.");

            $source = $pages[$page];
            $hasSummary = str_contains($source, self::SUMMARY_MARKER);

            foreach ($keys[1] as $key) {
                $atField = str_contains($source, "form.errors.{$key}")
                    || str_contains($source, "form.errors['{$key}']");

                if (! $atField && ! $hasSummary) {
                    $unrendered[] = "{$page}: {$key}";
                }
            }
        }

        $this->assertSame([], $unrendered, 'The CP rejects these but no page renders their error: '.implode(', ', $unrendered));
    }

    public function test_the_storage_switch_refuses_with_an_error_bag_rather_than_a_bare_status(): void
    {
        // `abort(422)` carries no errors, so Inertia hands the page nothing and
        // the refusal arrives as a blank stop. The settings page has one field
        // and one submission; if this regresses, its banner can never fill.
        $source = file_get_contents(dirname(__DIR__, 2).'/src/Http/Controllers/Cp/SettingsController.php');

        $this->assertStringNotContainsString(
            'abort_unless(in_array($target',
            $source,
            'switchStorage() refuses with a bare 422 again — no error bag, nothing for the page to show.'
        );

        $this->assertMatchesRegularExpression(
            '/validate\(\[\s*\'driver\'/',
            $source,
            'switchStorage() no longer validates `driver`, so a refused switch has no message.'
        );
    }
}
