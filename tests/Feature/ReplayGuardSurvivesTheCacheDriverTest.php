<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Auth\Support\ReplayProtectionService;
use Goldnead\WebhookManager\Tests\TestCase;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\NullStore;
use Illuminate\Cache\Repository;

/**
 * What the duplicate guard does when the cache cannot help it.
 *
 * The guard claims its key with a single conditional write, because the
 * read-then-write pair it replaced lost exactly the race it exists for. That
 * write reports failure two ways that look identical from the outside: "the key
 * is already there" and "this store never keeps anything". On the `null` driver
 * only the second is ever true, and a guard that cannot tell them apart answers
 * `409 Duplicate request.` to every delivery for ever — a working endpoint
 * turned off by a cache setting, which is a worse failure than the missing
 * guard it was meant to fix.
 */
class ReplayGuardSurvivesTheCacheDriverTest extends TestCase
{
    private function guard(object $store): ReplayProtectionService
    {
        return new ReplayProtectionService(new Repository($store), 600);
    }

    public function test_a_working_store_lets_the_first_through_and_stops_the_second(): void
    {
        $guard = $this->guard(new ArrayStore);

        $this->assertTrue($guard->check('endpoint:1:body:abc'));
        $this->assertFalse($guard->check('endpoint:1:body:abc'));
    }

    public function test_different_keys_do_not_block_each_other(): void
    {
        $guard = $this->guard(new ArrayStore);

        $this->assertTrue($guard->check('endpoint:1:body:abc'));
        $this->assertTrue($guard->check('endpoint:2:body:abc'));
    }

    public function test_a_store_that_remembers_nothing_does_not_reject_everything(): void
    {
        $guard = $this->guard(new NullStore);

        // No guard is possible here, and that is the operator's cache setting,
        // not something this package gets to answer with a dead endpoint.
        $this->assertTrue($guard->check('endpoint:1:body:abc'));
        $this->assertTrue($guard->check('endpoint:1:body:abc'));
    }

    /**
     * The claim is made with one conditional write, not a read followed by a
     * write.
     *
     * This is asserted at the call, not at an outcome, and that is deliberate.
     * Exclusivity under concurrency is the *store's* property — Redis `SET NX`,
     * Memcached `add` — and no single-process test can produce a real race
     * against it. What this package is responsible for is asking for it: making
     * the claim through `add()` and never through `get()` + `put()`, which is
     * the pair that lost the race before. A spy is the honest instrument for
     * that; an elaborate fake store would only be testing the fake.
     */
    public function test_the_claim_is_made_with_a_conditional_write(): void
    {
        $store = new class extends ArrayStore
        {
            /** @var list<string> */
            public array $aufrufe = [];

            public function add($key, $value, $seconds)
            {
                $this->aufrufe[] = 'add';

                return parent::get($key) === null && parent::put($key, $value, $seconds);
            }

            public function put($key, $value, $seconds)
            {
                $this->aufrufe[] = 'put';

                return parent::put($key, $value, $seconds);
            }
        };

        $guard = $this->guard($store);

        $this->assertTrue($guard->check('endpoint:1:body:abc'));

        $this->assertSame(['add'], $store->aufrufe,
            'one store call, and it is the conditional one');

        // The rejection path reads once to tell a duplicate apart from a store
        // that cannot remember. It must not write again.
        $store->aufrufe = [];
        $this->assertFalse($guard->check('endpoint:1:body:abc'));
        $this->assertNotContains('put', $store->aufrufe);
    }

    /**
     * `Repository::add()` only delegates to the store when the store has its
     * own `add()`. Redis, Memcached, the database and file stores do; the array
     * store the test bed uses does not, and falls back to get-then-put —
     * harmless exactly there, in a single process. Worth pinning rather than
     * assuming: the atomicity claim is about production drivers, and if a
     * future Laravel gives ArrayStore an `add()`, the note above is stale.
     */
    public function test_the_array_store_has_no_native_conditional_write(): void
    {
        $this->assertFalse(method_exists(ArrayStore::class, 'add'));
    }
}
