<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Tests\CpTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The Overview screen's three "create" CTAs are gated by pre-computed
 * permission flags in the controller. `canCreateRule` asked about
 * `manage rules`, an ability nobody registers, so the CTA never rendered —
 * on the first screen a new install shows.
 *
 * PermissionStringsAreRegisteredTest catches the typo structurally; this
 * catches the behaviour, including the case where someone gates a CTA on the
 * wrong (but registered) ability.
 */
class OverviewCreateCtasRenderTest extends CpTestCase
{
    use RefreshDatabase;

    public function test_a_super_user_gets_all_three_create_ctas(): void
    {
        $props = $this->overviewProps($this->superUser());

        $this->assertTrue($props['canCreateOutbound'], 'canCreateOutbound is false for a user with every ability.');
        $this->assertTrue($props['canCreateInbound'], 'canCreateInbound is false for a user with every ability.');
        $this->assertTrue($props['canCreateRule'], 'canCreateRule is false for a user with every ability.');
    }

    public function test_each_cta_is_gated_by_its_own_ability(): void
    {
        $matrix = [
            'canCreateOutbound' => 'manage outbound webhooks',
            'canCreateInbound' => 'manage inbound endpoints',
            'canCreateRule' => 'manage webhook rules',
        ];

        foreach ($matrix as $flag => $ability) {
            // 'view webhooks' alone gets the user past authorizeAny() but must
            // not unlock any create button.
            $props = $this->overviewProps($this->cpUser(['view webhooks']));
            $this->assertFalse($props[$flag], "{$flag} is true without '{$ability}'.");

            $props = $this->overviewProps($this->cpUser(['view webhooks', $ability]));
            $this->assertTrue($props[$flag], "{$flag} is false for a user holding '{$ability}'.");
        }
    }

    /** @return array<string, mixed> */
    protected function overviewProps($user): array
    {
        $response = $this
            ->actingAs($user)
            ->withHeaders($this->inertiaHeaders())
            ->getJson(cp_route('webhook-manager.overview'));

        $response->assertOk();

        return $response->json('props');
    }
}
