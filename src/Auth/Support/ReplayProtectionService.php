<?php

namespace Goldnead\WebhookManager\Auth\Support;

use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Replay protection for inbound endpoints. Stores recently seen
 * idempotency keys / signatures in cache for a configurable TTL.
 */
class ReplayProtectionService
{
    public function __construct(protected Cache $cache, protected int $ttlSeconds) {}

    public function seen(string $key): bool
    {
        return $this->cache->has($this->cacheKey($key));
    }

    public function remember(string $key): void
    {
        $this->cache->put($this->cacheKey($key), true, $this->ttlSeconds);
    }

    /**
     * Claim this key, atomically.
     *
     * One `add()` and not `seen()` + `remember()`. The read-then-write pair had
     * a window between the two calls, and the case that lands in that window is
     * the exact case this class exists for: a sender that did not get its
     * answer in time and sends the same delivery again immediately. Both
     * requests read "not seen", both proceed, and the configured action runs
     * twice — under concurrency the guard did nothing, and a sequential test
     * could never see it.
     *
     * `add()` is a single conditional write on every store that implements it —
     * Redis `SET NX`, Memcached `add`, the database store under a unique key,
     * the file store under a lock — which is what makes the claim exclusive.
     * `Repository::add()` falls back to get-then-put for stores without one
     * (the array store the test bed uses); that is the old race again, and it
     * is harmless exactly there, in a single process. A store that cannot
     * remember at all is handled below rather than mistaken for a duplicate.
     *
     * @return bool true if this key is fresh, false if it has been seen
     *              within the TTL window.
     */
    public function check(string $key): bool
    {
        $cacheKey = $this->cacheKey($key);

        if ($this->cache->add($cacheKey, true, $this->ttlSeconds)) {
            return true;
        }

        // `add()` said no, and there are two reasons it can. Either the key is
        // already there — a real duplicate, reject it — or the store cannot
        // remember anything at all. On the `null` driver every `add()` returns
        // false, so a bare `return $this->cache->add(...)` would answer 409 to
        // every single delivery: an endpoint that works nowhere instead of a
        // guard that works nowhere. The one extra read distinguishes them, and
        // it only happens on the rejection path.
        return ! $this->cache->has($cacheKey);
    }

    protected function cacheKey(string $key): string
    {
        return 'webhook-manager:replay:'.sha1($key);
    }
}
