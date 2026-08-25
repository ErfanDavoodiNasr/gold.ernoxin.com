<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class AutoPriceFetcher
{
    // ponytail: single global lock; fine at 1 fetch/min, per-source locks if multiple scrapers
    private const LOCK_KEY = 'gold:fetch-prices';
    // Keep ≥ schedule withoutOverlapping (2 min) so a slow scrape cannot double-run after lock expiry.
    private const LOCK_SECONDS = 120;
    private const RUNNING_STALE_SECONDS = 120;

    public function __construct(
        private PriceIngestor    $ingestor,
        private FetchStatusStore $fetchStatus,
    )
    {
    }

    public function fetchIfDue(bool $force = false): array
    {
        if (!$force && !$this->isDue()) {
            return ['status' => 'skipped'];
        }

        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);
        if (!$lock->get()) {
            return ['status' => 'skipped', 'reason' => 'locked'];
        }

        try {
            $result = $this->ingestor->fetchAndStore();

            return array_merge(['status' => 'success'], $result);
        } catch (\Throwable $e) {
            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        } finally {
            $lock->release();
        }
    }

    public function isDue(): bool
    {
        if ($this->fetchStatus->isActivelyRunning(self::RUNNING_STALE_SECONDS)) {
            return false;
        }

        $interval = max(1, (int)config('gold.fetch_interval_minutes', 5));
        $now = now();

        if ($now->minute % $interval !== 0) {
            return false;
        }

        $slotStart = $now->copy()->second(0);
        $lastSuccess = $this->fetchStatus->lastSuccessStartedAt();

        if ($lastSuccess && $lastSuccess->gte($slotStart)) {
            return false;
        }

        return true;
    }
}
