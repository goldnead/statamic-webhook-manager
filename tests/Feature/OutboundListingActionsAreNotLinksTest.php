<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Tests\TestCase;

/**
 * Two UI regressions on the outbound listing, both invisible to any PHP test
 * that only looks at routes:
 *
 *   - The "Test" row action was rendered as `<DropdownItem :href="test_url">`,
 *     i.e. a link. `test_url` points at a POST-only route, so clicking it
 *     issued a GET and produced a 404 without ever sending anything.
 *   - After "Disable", the row kept showing "Active". <Listing> holds its own
 *     copy of the rows, so an Inertia prop reload never reached it and only a
 *     full page reload showed the truth — while the database was already 0.
 *
 * The package ships no JS test runner, so these are locked structurally
 * against the source of the page. The behavioural proof lives in the browser
 * QA area (`hub-qa/tools/areas/webhooks-fixes.mjs`).
 */
class OutboundListingActionsAreNotLinksTest extends TestCase
{
    private function source(): string
    {
        $path = __DIR__.'/../../resources/js/pages/outbound/Index.vue';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_the_test_row_action_posts_instead_of_linking(): void
    {
        $source = $this->source();

        $this->assertDoesNotMatchRegularExpression(
            '/:href="hook\.test_url"/',
            $source,
            'The Test row action links to a POST-only route — a GET there is a guaranteed 404.'
        );

        $this->assertMatchesRegularExpression(
            '/@click="runTest\(hook\)"/',
            $source,
            'The Test row action must trigger a POST handler.'
        );

        $this->assertStringContainsString(
            'axios.post(hook.test_url',
            $source,
            'runTest() must POST to the test endpoint.'
        );
    }

    public function test_toggling_updates_the_row_without_a_full_reload(): void
    {
        $source = $this->source();

        $this->assertMatchesRegularExpression(
            '/onSuccess:\s*\(\)\s*=>\s*\{\s*hook\.enabled\s*=\s*!hook\.enabled/',
            $source,
            'The Status badge must follow the toggle immediately; <Listing> does not see Inertia prop updates.'
        );
    }
}
