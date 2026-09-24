<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\BrandContext\Settings\SettingsRegistry;
use Goldnead\WebhookManager\Tests\TestCase;
use Goldnead\WebhookManager\WebhookManagerServiceProvider;
use Mockery;
use Orchestra\Testbench\TestCase as TestbenchTestCase;

/**
 * The settings registration must find the addon's translations in place.
 *
 * Since brand-context 1.14 `SettingsRegistry::register()` catches up on stored
 * settings right away, and that reads `Settings::settingsGroups()`, which is
 * one `__()` per label. `bootAddon()` registered the settings before it loaded
 * its own translation namespace, so that first read found no
 * `webhook-manager::settings` file and Laravel remembered the group as empty
 * for the rest of the process: every label on the settings screen printed its
 * key. Only the MySQL leg saw it, because only there a stored row survives
 * from an earlier test and makes the catch-up do something.
 */
class TranslationsLoadBeforeSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        // Skip TestCase::setUp(): it runs bootAddon() already, and this test
        // is about what bootAddon() does first.
        TestbenchTestCase::setUp();
    }

    public function test_the_translation_namespace_exists_when_the_settings_register(): void
    {
        $seen = null;

        $registry = Mockery::mock(SettingsRegistry::class)->shouldIgnoreMissing();
        $registry->shouldReceive('register')->andReturnUsing(function () use (&$seen) {
            $seen = __('webhook-manager::settings.groups.features.title', [], 'en');
        });
        $this->app->instance(SettingsRegistry::class, $registry);

        $this->app->getProvider(WebhookManagerServiceProvider::class)->bootAddon();

        $this->assertNotNull($seen, 'bootAddon() did not register the settings.');
        $this->assertStringNotContainsString('webhook-manager::', $seen);
        $this->assertStringNotContainsString(
            'webhook-manager::',
            __('webhook-manager::settings.groups.features.title', [], 'en'),
            'The early lookup left the settings group cached as empty.',
        );
    }
}
