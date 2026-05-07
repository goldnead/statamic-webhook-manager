<?php

namespace Goldnead\WebhookManager\Auth\Support;

use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Replay protection for inbound endpoints. Stores recently seen
 * idempotency keys / signatures in cache for a configurable TTL.
 */
class ReplayProtectionService
{
    public function __construct(protected Cache $cache, protected int $ttlSeconds)
    {
    }

    public function seen(string $key): bool
    {
        return $this->cache->has($this->cacheKey($key));
    }

    public function remember(string $key): void
    {
        $this->cache->put($this->cacheKey($key), true, $this->ttlSeconds);
    }

    /**
     * @return bool true if this key is fresh, false if it has been seen
     *              within the TTL window.
     */
    public function check(string $key): bool
    {
        if ($this->seen($key)) {
            return false;
        }
        $this->remember($key);
        return true;
    }

    protected function cacheKey(string $key): string
    {
        return 'webhook-manager:replay:'.sha1($key);
    }
}
