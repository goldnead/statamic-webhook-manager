<?php

namespace Goldnead\WebhookManager\Auth\Support;

use Goldnead\WebhookManager\Support\CacheClaim;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Replay protection for inbound endpoints. Stores recently seen
 * idempotency keys / signatures in cache for a configurable TTL.
 */
class ReplayProtectionService
{
    public function __construct(protected Cache $cache, protected int $ttlSeconds) {}

    /**
     * @deprecated 2.1.2 Not the guard. `seen()` followed by `remember()` is the
     *             read-then-write pair that loses the race this class exists
     *             for; it is kept only because it is public API. Use `check()`.
     */
    public function seen(string $key): bool
    {
        return $this->cache->has($this->cacheKey($key));
    }

    /**
     * @deprecated 2.1.2 See `seen()`. Use `check()`.
     */
    public function remember(string $key): void
    {
        $this->cache->put($this->cacheKey($key), true, $this->ttlSeconds);
    }

    /**
     * Claim this key.
     *
     * The exclusivity, the `null`-driver trap and the zero-TTL case all live in
     * {@see CacheClaim} — read it before changing anything here. In short: one
     * conditional write, and a store that keeps nothing must not be mistaken
     * for a store that already has the key.
     *
     * @return bool true if this key is fresh, false if it has been seen
     *              within the TTL window.
     */
    public function check(string $key): bool
    {
        return CacheClaim::first($this->cache, $this->cacheKey($key), $this->ttlSeconds);
    }

    protected function cacheKey(string $key): string
    {
        return 'webhook-manager:replay:'.sha1($key);
    }
}
