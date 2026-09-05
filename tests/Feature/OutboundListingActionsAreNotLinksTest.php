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

    /**
     * Superseded on 05.09.2026, and the replacement is what this now locks.
     *
     * Enable/Disable used to be a hand-rolled `router.patch()` row action that
     * flipped `hook.enabled` locally, because <Listing> keeps its own copy of
     * the rows. Since the listing has a real action endpoint, the same job is
     * done by the native actions `webhook_manager_enable_outbound` /
     * `_disable_outbound`, and core's RowActions refreshes the listing itself.
     *
     * Leaving both in place produced two "Disable" entries in one "…" menu, one
     * of them untranslated. A hand-rolled toggle coming back next to the native
     * action is what fails here.
     */
    public function test_enabling_is_a_native_action_not_a_second_hand_rolled_toggle(): void
    {
        $source = $this->source();

        $this->assertStringNotContainsString(
            'hook.toggle_url',
            $source,
            'A hand-rolled toggle is back on the listing next to the native Enable/Disable action — '
            .'that is the duplicate "…" entry this test exists to keep out.'
        );

        $this->assertStringNotContainsString(
            "__('Disable')",
            $source,
            'The old untranslated Disable label is back on the listing.'
        );
    }

    /**
     * `actionUrl` used to point at the index route, which gave the listing a
     * checkbox column and an empty bulk menu. Without a real action endpoint
     * the native Enable/Disable above cannot exist at all.
     */
    public function test_the_listing_points_at_the_real_action_endpoint(): void
    {
        $controller = (string) file_get_contents(__DIR__.'/../../src/Http/Controllers/Cp/OutboundController.php');

        $this->assertStringContainsString(
            "cp_route('webhook-manager.outbound.actions.run')",
            $controller,
            'actionUrl no longer points at the action endpoint; the checkbox column would be decoration.'
        );
    }
}
