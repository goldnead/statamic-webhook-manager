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
     * `add()` is a single conditional write on every cache store that matters
     * here (Redis `SET NX`, Memcached `add`, and the database store under a
     * unique key), which is what makes the claim exclusive.
     *
     * @return bool true if this key is fresh, false if it has been seen
     *              within the TTL window.
     */
    public function check(string $key): bool
    {
        return $this->cache->add($this->cacheKey($key), true, $this->ttlSeconds);
    }

    protected function cacheKey(string $key): string
    {
        return 'webhook-manager:replay:'.sha1($key);
    }
}
