<?php

namespace Goldnead\WebhookManager\Support;

use Illuminate\Contracts\Cache\Repository;

/**
 * "Am I the first to see this key inside this window?"
 *
 * Two places in this package ask that question — the inbound replay guard, and
 * the log lines that must not repeat themselves once an hour — and both got it
 * subtly wrong in different ways before this class existed. It is one place now
 * because the subtlety is not in either caller, it is in `Cache::add()`.
 *
 * ## `false` from `add()` has more than one cause
 *
 * `Repository::add()` answers `false` when the key is already there. It also
 * answers `false` when the store keeps nothing at all (the `null` driver), and
 * when the TTL is zero or negative. Treating all three as "already seen" is how
 * 2.1.0 turned a `null` cache into an endpoint that answered `409` to every
 * delivery — a cache setting switching a working integration off.
 *
 * So a `false` is followed by one read. If the key is really there, someone
 * else claimed it. If it is not, the store did not keep what it was just
 * handed, and the honest answer is "you are first" — no guard is possible, and
 * pretending otherwise breaks the thing being guarded rather than protecting
 * it.
 *
 * ## Why not read first
 *
 * Because that is the race this replaces. `has()` then `put()` lets two
 * simultaneous callers both read "absent" and both proceed, which is exactly
 * the case a replay guard exists for: a sender that did not get its answer in
 * time and immediately sends again. The conditional write goes first, so
 * exclusivity is the store's (`SET NX` on Redis, `add` on Memcached, a unique
 * key in the database store). The read only happens on the losing path, where
 * there is nothing left to race for.
 *
 * `Repository::add()` falls back to get-then-put for stores with no native
 * `add()` — the array store the test bed uses. That is the old race again, and
 * it is harmless exactly there, in a single process.
 */
final class CacheClaim
{
    /**
     * @param  string  $key  fully qualified cache key, prefix included
     * @param  int  $seconds  window length; zero or less means "no window"
     * @return bool true when the caller is the first (or when no guard is possible)
     */
    public static function first(Repository $cache, string $key, int $seconds): bool
    {
        // No window means nothing to be exclusive about. Said out loud rather
        // than left to `add()`, which answers `false` here and would otherwise
        // read as "already seen".
        if ($seconds <= 0) {
            return true;
        }

        if ($cache->add($key, true, $seconds)) {
            return true;
        }

        return ! $cache->has($key);
    }
}
