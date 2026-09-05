<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Actions\Cp\DeleteOutboundWebhook;
use Goldnead\WebhookManager\Actions\Cp\DisableOutboundWebhook;
use Goldnead\WebhookManager\Actions\Cp\EnableOutboundWebhook;
use Goldnead\WebhookManager\Actions\Cp\ReplayDelivery;
use Goldnead\WebhookManager\Domain\Delivery\Models\Delivery;
use Goldnead\WebhookManager\Domain\OutboundWebhook\Models\OutboundWebhook;
use Goldnead\WebhookManager\Http\Controllers\Cp\DeliveryActionController;
use Goldnead\WebhookManager\Http\Controllers\Cp\OutboundActionController;
use Goldnead\WebhookManager\Jobs\ReplayDeliveryJob;
use Goldnead\WebhookManager\Tests\CpTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

/**
 * The bulk/row action endpoints the listings post to.
 *
 * Both were added on 05.09.2026 so the checkbox column stops being decoration,
 * and both shipped with two holes that a plain POST found within a minute.
 * Nothing in `tests/` touched the action layer until this file, which is why
 * neither hole was caught by 342 green tests.
 *
 *   1. **An empty selection reached core.** `getSelectedItems()` drops ids it
 *      cannot resolve (deleted row, stale page, another brand's id). When it
 *      drops *all* of them, core is handed an EMPTY collection — and
 *      `Action::visibleToBulk()`/`authorizeBulk()` decide by comparing counts
 *      (`vendor/statamic/cms/src/Actions/Action.php:42-49,56-63`), so `0 === 0`
 *      makes **every** action of **every** installed addon apply.
 *      `ActionRepository::forBulk()` then walked the global list and died
 *      inside a foreign action's `fieldItems()`: HTTP 500 with a stack trace
 *      full of absolute server paths. Had it not crashed, it would have
 *      answered with the complete action list of every addon on the install.
 *
 *   2. **An action from the other endpoint ran and reported success.** Core's
 *      `run()` resolves the handle from the global registry and checks only
 *      `authorize()`; `visibleTo()` is a UI filter it never consults
 *      (`ActionController.php:26-36`). Posting
 *      `webhook_manager_replay_delivery` to the *outbound* endpoint replayed an
 *      OutboundWebhook as a delivery and answered `success: true`; the id then
 *      went to `ReplayDeliveryJob`, which resolves with `Delivery::find()`
 *      outside the CP's brand context.
 *
 * **What this harness can and cannot drive.** Statamic's `ExtensionServiceProvider`
 * is not loaded by the package testbench, so `app('statamic.actions')` does not
 * exist here and core's `run()` cannot be reached over HTTP. That is fine for
 * both defects: they live in what the endpoint accepts *before* core is asked,
 * and that half is exercised over the real HTTP path below. The other half —
 * what each action decides and does — is exercised against the classes, which
 * need no container. The full round trip is verified in the playground; see
 * the handover for the recorded status codes.
 */
class ListingActionEndpointsAreScopedTest extends CpTestCase
{
    use RefreshDatabase;

    private function makeWebhook(array $overrides = []): OutboundWebhook
    {
        return OutboundWebhook::create(array_merge([
            'name' => 'Action hook',
            'handle' => 'action-hook-'.bin2hex(random_bytes(4)),
            'enabled' => true,
            'trigger_type' => 'entry.saved',
            'url' => 'https://receiver.example.test/hook',
            'method' => 'POST',
            'auth_type' => 'none',
            'payload_type' => 'raw_json',
            'payload_template' => '{"x":1}',
            'queue_enabled' => false,
        ], $overrides));
    }

    private function makeDelivery(array $overrides = []): Delivery
    {
        return Delivery::create(array_merge([
            'outbound_webhook_id' => $this->makeWebhook()->id,
            'trigger_type' => 'entry.saved',
            'status' => Delivery::STATUS_FAILED,
            'request_url' => 'https://receiver.example.test/hook',
            'request_method' => 'POST',
            'attempts' => 1,
        ], $overrides));
    }

    // ── 1. An empty or unresolvable selection never reaches core ────────────

    public function test_an_unknown_id_yields_no_actions_on_the_outbound_list_endpoint(): void
    {
        $response = $this->actingAs($this->superUser())
            ->postJson(cp_route('webhook-manager.outbound.actions.list'), [
                'selections' => ['999999'],
            ]);

        // Was HTTP 500 out of Statamic\Actions\MoveAsset::fieldItems().
        $response->assertOk();
        $this->assertSame([], $response->json(), 'An unresolvable selection must yield no actions at all.');
    }

    public function test_an_unknown_id_yields_no_actions_on_the_delivery_list_endpoint(): void
    {
        $response = $this->actingAs($this->superUser())
            ->postJson(cp_route('webhook-manager.deliveries.actions.list'), [
                'selections' => ['273', '274'],
            ]);

        $response->assertOk();
        $this->assertSame([], $response->json());
    }

    /**
     * The real damage of the empty-collection path was not the crash — it was
     * what a non-crashing install would have answered: every action of every
     * installed addon, because `0 === 0` passes `visibleToBulk()`.
     */
    public function test_the_list_endpoint_cannot_leak_foreign_addons_actions(): void
    {
        $body = $this->actingAs($this->superUser())
            ->postJson(cp_route('webhook-manager.outbound.actions.list'), ['selections' => ['999999']])
            ->assertOk()
            ->json();

        $this->assertSame([], $body);
    }

    public function test_running_an_action_on_a_vanished_record_is_a_404_not_a_success(): void
    {
        $this->actingAs($this->superUser())
            ->postJson(cp_route('webhook-manager.outbound.actions.run'), [
                'action' => DisableOutboundWebhook::handle(),
                'selections' => ['999999'],
                'values' => [],
            ])
            ->assertNotFound();
    }

    public function test_running_a_replay_on_a_vanished_delivery_is_a_404(): void
    {
        Queue::fake();

        $this->actingAs($this->superUser())
            ->postJson(cp_route('webhook-manager.deliveries.actions.run'), [
                'action' => ReplayDelivery::handle(),
                'selections' => ['999999'],
                'values' => [],
            ])
            ->assertNotFound();

        Queue::assertNothingPushed();
    }

    // ── 2. An action that does not belong to this endpoint ──────────────────

    public function test_the_outbound_endpoint_refuses_the_delivery_replay_action(): void
    {
        Queue::fake();
        $hook = $this->makeWebhook();

        // Answered 200 with "Zustellung zum erneuten Senden eingereiht." before.
        $this->actingAs($this->superUser())
            ->postJson(cp_route('webhook-manager.outbound.actions.run'), [
                'action' => ReplayDelivery::handle(),
                'selections' => [(string) $hook->id],
                'values' => [],
            ])
            ->assertNotFound();

        Queue::assertNothingPushed();
        $this->assertNotNull($hook->fresh());
    }

    public function test_the_delivery_endpoint_refuses_the_outbound_delete_action(): void
    {
        $delivery = $this->makeDelivery();
        $hooksBefore = OutboundWebhook::count();

        $this->actingAs($this->superUser())
            ->postJson(cp_route('webhook-manager.deliveries.actions.run'), [
                'action' => DeleteOutboundWebhook::handle(),
                'selections' => [(string) $delivery->id],
                'values' => [],
            ])
            ->assertNotFound();

        $this->assertSame($hooksBefore, OutboundWebhook::count());
    }

    public function test_a_foreign_addons_action_handle_is_refused(): void
    {
        $hook = $this->makeWebhook();

        $this->actingAs($this->superUser())
            ->postJson(cp_route('webhook-manager.outbound.actions.run'), [
                'action' => 'delete',   // core's own entry/asset delete action
                'selections' => [(string) $hook->id],
                'values' => [],
            ])
            ->assertNotFound();

        $this->assertNotNull($hook->fresh(), 'A foreign action reached a webhook.');
    }

    /**
     * The allowlist each controller declares is the invariant the guard rests
     * on: neither endpoint may serve the other's handles, and neither may serve
     * a handle nobody in this addon registers.
     */
    public function test_each_endpoint_declares_only_its_own_action_handles(): void
    {
        // Resolved through the container: CpController takes a Request.
        $outbound = $this->allowedActionsOf(app(OutboundActionController::class));
        $deliveries = $this->allowedActionsOf(app(DeliveryActionController::class));

        $this->assertEqualsCanonicalizing([
            EnableOutboundWebhook::handle(),
            DisableOutboundWebhook::handle(),
            DeleteOutboundWebhook::handle(),
        ], $outbound);

        $this->assertSame([ReplayDelivery::handle()], $deliveries);
        $this->assertSame([], array_intersect($outbound, $deliveries));
    }

    /** @return array<int, string> */
    private function allowedActionsOf(object $controller): array
    {
        $method = new \ReflectionMethod($controller, 'allowedActions');
        $method->setAccessible(true);

        return $method->invoke($controller);
    }

    // ── 3. What each action decides ─────────────────────────────────────────

    /**
     * `visibleTo()` is only a UI filter, so `authorize()` carries the type check
     * as well — core asks `authorize()` per item inside `run()` and aborts with
     * 403 when any item fails.
     */
    public function test_each_action_authorizes_only_its_own_model_type(): void
    {
        $user = $this->superUser();
        $hook = $this->makeWebhook();
        $delivery = $this->makeDelivery();

        foreach ([EnableOutboundWebhook::class, DisableOutboundWebhook::class, DeleteOutboundWebhook::class] as $class) {
            $action = new $class;
            $this->assertTrue($action->authorize($user, $hook), $class.' refuses its own model.');
            $this->assertFalse($action->authorize($user, $delivery), $class.' authorizes a Delivery.');
        }

        $replay = new ReplayDelivery;
        $this->assertTrue($replay->authorize($user, $delivery));
        $this->assertFalse($replay->authorize($user, $hook), 'ReplayDelivery authorizes an OutboundWebhook.');
    }

    public function test_an_action_refuses_a_user_without_the_ability(): void
    {
        $reader = $this->cpUser(['view webhooks', 'view webhook deliveries']);
        $hook = $this->makeWebhook();
        $delivery = $this->makeDelivery();

        $this->assertFalse((new DisableOutboundWebhook)->authorize($reader, $hook));
        $this->assertFalse((new DeleteOutboundWebhook)->authorize($reader, $hook));
        $this->assertFalse((new ReplayDelivery)->authorize($reader, $delivery));

        $this->assertTrue((new DisableOutboundWebhook)->authorize(
            $this->cpUser(['manage outbound webhooks']), $hook
        ));
        $this->assertTrue((new ReplayDelivery)->authorize(
            $this->cpUser(['replay webhook deliveries']), $delivery
        ));
    }

    /**
     * Enable and Disable are two actions rather than one toggle, so the bulk
     * bar can only offer a direction that applies to every checked row.
     */
    public function test_enable_and_disable_are_visible_only_in_the_state_they_can_change(): void
    {
        $on = $this->makeWebhook(['enabled' => true]);
        $off = $this->makeWebhook(['enabled' => false]);

        $this->assertTrue((new DisableOutboundWebhook)->visibleTo($on));
        $this->assertFalse((new DisableOutboundWebhook)->visibleTo($off));
        $this->assertTrue((new EnableOutboundWebhook)->visibleTo($off));
        $this->assertFalse((new EnableOutboundWebhook)->visibleTo($on));

        // And neither ever shows up on a foreign listing.
        $this->assertFalse((new EnableOutboundWebhook)->visibleTo($this->makeDelivery()));
    }

    public function test_replay_is_visible_only_for_a_replayable_delivery(): void
    {
        $this->assertTrue((new ReplayDelivery)->visibleTo($this->makeDelivery()));
        $this->assertFalse((new ReplayDelivery)->visibleTo(
            $this->makeDelivery(['status' => Delivery::STATUS_SUCCESS])
        ));
        $this->assertFalse((new ReplayDelivery)->visibleTo($this->makeWebhook()));
    }

    // ── 4. What each action does ────────────────────────────────────────────

    public function test_disable_then_enable_reaches_the_database(): void
    {
        $a = $this->makeWebhook(['enabled' => true]);
        $b = $this->makeWebhook(['enabled' => true]);

        (new DisableOutboundWebhook)->run(collect([$a, $b]), []);
        $this->assertFalse((bool) $a->fresh()->enabled);
        $this->assertFalse((bool) $b->fresh()->enabled);

        (new EnableOutboundWebhook)->run(collect([$a->fresh(), $b->fresh()]), []);
        $this->assertTrue((bool) $a->fresh()->enabled);
        $this->assertTrue((bool) $b->fresh()->enabled);
    }

    public function test_delete_removes_the_webhooks(): void
    {
        $a = $this->makeWebhook();
        $b = $this->makeWebhook();

        (new DeleteOutboundWebhook)->run(collect([$a, $b]), []);

        $this->assertNull($a->fresh());
        $this->assertNull($b->fresh());
    }

    public function test_replay_queues_one_job_per_delivery(): void
    {
        Queue::fake();
        $a = $this->makeDelivery();
        $b = $this->makeDelivery();

        (new ReplayDelivery)->run(collect([$a, $b]), []);

        Queue::assertPushed(ReplayDeliveryJob::class, 2);
    }

    /**
     * The third lock. If a foreign model ever got past the endpoint allowlist
     * and past `authorize()`, `run()` must fail loudly rather than put a
     * webhook id on the delivery-replay queue.
     */
    public function test_replay_throws_rather_than_queueing_a_foreign_model(): void
    {
        Queue::fake();

        $this->expectException(\RuntimeException::class);

        try {
            (new ReplayDelivery)->run(collect([$this->makeWebhook()]), []);
        } finally {
            Queue::assertNothingPushed();
        }
    }
}
