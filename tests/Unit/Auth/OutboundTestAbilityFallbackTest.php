<?php

namespace Goldnead\WebhookManager\Tests\Unit\Auth;

use Goldnead\WebhookManager\Http\Controllers\Cp\OutboundController;
use Goldnead\WebhookManager\Tests\FakeCpUser;
use PHPUnit\Framework\TestCase;

/**
 * The "Test" flag on the outbound screens used to be built like this:
 *
 *     'canTest' => (bool) ($user?->can('test outbound webhooks')
 *                          ?? $user?->can('manage outbound webhooks')),
 *
 * `??` fires on NULL. `can()` returns a boolean — so the right-hand side was
 * dead code from the day it was written, and when the dedicated ability
 * answered `false` (it was never registered, so it answered `false` for
 * everyone, super users included) the button vanished for every user in the
 * installation. Nothing logged, nothing failed, the endpoint behind the
 * button worked the whole time.
 *
 * `??` where `||` was meant is invisible in review precisely because the
 * fallback *looks* present. The characteristic of the bug is that it only
 * shows up with a falsy-but-not-null left-hand side, which is why this test
 * drives the resolution with a user that answers a hard `false` — a test that
 * passes `null` would pass against the broken version too.
 *
 * The route-level consequences are covered by
 * tests/Feature/OutboundTestActionIsReachableTest.php; this pins the decision
 * itself, in isolation and without a booted framework.
 */
class OutboundTestAbilityFallbackTest extends TestCase
{
    /**
     * The regression test. `FakeCpUser::can()` returns a strict boolean, so
     * the dedicated ability answers `false`, never `null`.
     */
    public function test_the_fallback_fires_when_the_dedicated_ability_answers_false(): void
    {
        $user = new FakeCpUser(['view webhooks', 'manage outbound webhooks']);

        $this->assertFalse(
            $user->can('test outbound webhooks'),
            'Precondition: the left-hand side must be false, not null — otherwise this test proves nothing.'
        );

        $this->assertTrue(
            OutboundController::canTest($user),
            'Someone who may manage the hook may fire a test request against it; '
            .'`??` never falls back on a `false`.'
        );
    }

    public function test_the_dedicated_ability_alone_is_enough(): void
    {
        $this->assertTrue(
            OutboundController::canTest(new FakeCpUser(['view webhooks', 'test outbound webhooks']))
        );
    }

    public function test_a_user_with_neither_ability_may_not_test(): void
    {
        $this->assertFalse(
            OutboundController::canTest(new FakeCpUser(['view webhooks', 'view webhook deliveries']))
        );
    }

    public function test_a_guest_may_not_test(): void
    {
        $this->assertFalse(OutboundController::canTest(null));
    }
}
