<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Cache::remember with a lock so concurrent misses do not all hit the DB.
 * Values use the default store (apc/file on cPanel). Locks always use the
 * file store — APC has no LockProvider on this Laravel version.
 */
class StampedeCache
{
    public static function remember(string $key, $ttl, Closure $callback)
    {
        $hit = Cache::get($key);
        if ($hit !== null) {
            return $hit;
        }

        try {
            return Cache::store('file')->lock("lock:{$key}", 15)->block(8, function () use ($key, $ttl, $callback) {
                $hit = Cache::get($key);
                if ($hit !== null) {
                    return $hit;
                }

                $value = $callback();
                Cache::put($key, $value, $ttl);

                return $value;
            });
        } catch (LockTimeoutException $e) {
            usleep(200_000);
            $hit = Cache::get($key);
            if ($hit !== null) {
                return $hit;
            }

            return Cache::remember($key, $ttl, $callback);
        }
    }
}
