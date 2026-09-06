<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Auth\Support\SecretMasker;
use Goldnead\WebhookManager\Storage\StorageDriverManager;
use Goldnead\WebhookManager\Tests\CpTestCase;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The three panels that came to the Debug screen from the deleted settings one.
 *
 * When a screen moves, the half of it that was never a setting is the half that
 * quietly disappears: the deployment-owned values an operator checks, the
 * resolved config tree, and the storage driver switch. None of them belongs in
 * the suite's shared settings layer — they are diagnostics and an action — so
 * they came here, and this is what says they arrived.
 */
class DebugScreenPanelsTest extends CpTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The chosen driver is a file in `storage/`, not a database row, so it
        // survives from one test into the next in the same process — and the
        // switch test writes it. Cleared here so every test starts on the
        // packaged `eloquent`.
        @unlink($this->app->make(StorageDriverManager::class)->settingsPath());
    }

    /** @return array<string, mixed> */
    protected function debugProps(?Authenticatable $as = null): array
    {
        return ($as ? $this->actingAs($as) : $this->actingAs($this->superUser()))
            ->withHeaders($this->inertiaHeaders())
            ->get(cp_route('webhook-manager.debug'))
            ->assertOk()
            ->json('props');
    }

    public function test_it_shows_the_deployment_owned_values(): void
    {
        $props = $this->debugProps();

        $this->assertNotEmpty($props['environment']);

        $envKeys = array_column($props['environment'], 'env');

        // Read-only on purpose: a database row that outranks an env var is a
        // setting that changes back on the next deploy without anyone touching
        // a screen. The inbound prefix is in here for a different reason —
        // `route:cache` freezes it, so a changed value prints URLs that answer
        // 404 with nothing to say why.
        $this->assertContains('WEBHOOK_MANAGER_QUEUE_NAME', $envKeys);
        $this->assertContains('config/webhook-manager.php', $envKeys);
    }

    public function test_it_shows_the_resolved_config_and_where_the_file_is(): void
    {
        $props = $this->debugProps();

        $this->assertStringEndsWith('webhook-manager.php', $props['configFilePath']);

        $config = json_decode($props['rawConfig'], true);

        $this->assertIsArray($config);
        $this->assertArrayHasKey('retry', $config);
    }

    public function test_it_does_not_print_the_alert_credential_into_the_page(): void
    {
        // The diagnostics panel prints the resolved config tree so an operator
        // can see what the installation actually resolved to. The chat alert
        // URL *is* the credential — anybody holding it can post into that
        // channel — and printed verbatim it lands in screen shares, screenshots
        // and every front-end error report.
        //
        // Asserted against the whole page payload, not against the one prop: a
        // secret that reappears somewhere else in the props is the same leak,
        // and a test aimed at `rawConfig` alone would miss it.
        $url = 'https://hooks.slack.com/services/T000/B000/xoxbSuperSecretValue';
        config()->set('webhook-manager.alerts.slack.webhook_url', $url);

        $response = $this->actingAs($this->superUser())
            ->withHeaders($this->inertiaHeaders())
            ->get(cp_route('webhook-manager.debug'))
            ->assertOk();

        $this->assertStringNotContainsString($url, $response->getContent());
        $this->assertStringNotContainsString('xoxbSuperSecretValue', $response->getContent());

        // Masked, not dropped. The operator still has to be able to tell that a
        // value is set and to recognise which one it is.
        //
        // Compared against what the masker actually produces, encoded the way
        // the panel encodes it — the tree goes out without
        // JSON_UNESCAPED_UNICODE, so a hand-typed bullet in this file would
        // never match what the browser receives.
        $raw = $response->json('props.rawConfig');
        $expected = trim(json_encode(SecretMasker::mask($url)), '"');

        $this->assertStringContainsString('http', $raw);
        $this->assertStringContainsString('alue', $raw);
        $this->assertStringContainsString($expected, $raw);
    }

    public function test_it_shows_the_storage_driver_and_where_to_switch_it(): void
    {
        $props = $this->debugProps();

        $this->assertTrue($props['canManageSettings']);
        $this->assertSame('eloquent', $props['storage']['driver']);
        $this->assertSame(cp_route('webhook-manager.debug.storage'), $props['storage']['switch_url']);
        $this->assertArrayHasKey('counts', $props['storage']);
    }

    public function test_the_storage_switch_still_works_from_here(): void
    {
        $this->actingAs($this->superUser())
            ->withHeaders($this->inertiaHeaders())
            ->post(cp_route('webhook-manager.debug.storage'), ['driver' => 'flat'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('flat', $this->debugProps()['storage']['driver']);
    }

    public function test_the_storage_switch_refuses_a_driver_that_does_not_exist(): void
    {
        // With an error bag, not a bare 422: `abort(422)` carries no errors, so
        // Inertia hands the page nothing and the refusal arrives as a blank
        // stop.
        $this->actingAs($this->superUser())
            ->withHeaders($this->inertiaHeaders())
            ->post(cp_route('webhook-manager.debug.storage'), ['driver' => 'redis'])
            ->assertSessionHasErrors('driver');

        $this->assertSame('eloquent', $this->debugProps()['storage']['driver']);
    }

    public function test_it_refuses_the_storage_switch_without_the_settings_permission(): void
    {
        // The page can be reached with `use webhook debug tools` alone; moving
        // the driver cannot.
        $this->actingAs($this->cpUser(['use webhook debug tools']))
            ->withHeaders($this->inertiaHeaders())
            ->post(cp_route('webhook-manager.debug.storage'), ['driver' => 'flat'])
            ->assertStatus(403);
    }

    public function test_a_settings_manager_reaches_the_page_but_not_the_debug_actions(): void
    {
        // The panels moved here from a screen that `manage webhook settings`
        // opened, so that ability has to keep reaching them — otherwise the
        // move quietly takes the CP's only storage switch away from the people
        // who own it. It does not buy the debug tools: the preview and simulate
        // endpoints check `use webhook debug tools` in their own controllers,
        // so their URLs are withheld and the panels stay hidden.
        $props = $this->debugProps($this->cpUser(['manage webhook settings']));

        $this->assertNull($props['previewUrl']);
        $this->assertNull($props['simulateUrl']);
        $this->assertTrue($props['canManageSettings']);
        $this->assertNotNull($props['storage']);
    }

    public function test_a_debug_user_without_the_settings_permission_sees_no_storage_panel(): void
    {
        $props = $this->debugProps($this->cpUser(['use webhook debug tools']));

        $this->assertFalse($props['canManageSettings']);
        $this->assertNull($props['storage']);
        $this->assertNotNull($props['previewUrl']);
    }

    public function test_it_refuses_somebody_with_neither_ability(): void
    {
        $this->actingAs($this->cpUser(['view webhooks']))
            ->withHeaders($this->inertiaHeaders())
            ->get(cp_route('webhook-manager.debug'))
            ->assertStatus(403);
    }
}
