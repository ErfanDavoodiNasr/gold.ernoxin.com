<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class AutoPriceFetcher
{
    // ponytail: single global lock; fine at 1 fetch/min, per-source locks if multiple scrapers
    private const LOCK_KEY = 'gold:fetch-prices';

    public function __construct(
        private PriceIngestor    $ingestor,
        private FetchStatusStore $fetchStatus,
    )
    {
    }

    public function fetchIfDue(bool $force = false): array
    {
        try {
            if (!$force && !$this->isDue()) {
                return ['status' => 'skipped', 'reason' => 'interval'];
            }
        } catch (\Throwable $e) {
            return [
                'status' => 'failed',
                'error' => 'بررسی زمان دریافت ناموفق بود: ' . $e->getMessage(),
            ];
        }

        // File store: APC has no LockProvider on this Laravel version (same as StampedeCache).
        try {
            $lock = Cache::store('file')->lock(self::LOCK_KEY, $this->lockSeconds());
        } catch (\Throwable $e) {
            return [
                'status' => 'failed',
                'error' => 'قفل دریافت در دسترس نیست: ' . $e->getMessage(),
            ];
        }

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
        if ($this->fetchStatus->isActivelyRunning($this->lockSeconds())) {
            return false;
        }

        // Invalid/negative/huge env values: clamp to [1, 1440] minutes.
        $interval = (int)config('gold.fetch_interval_minutes', 5);
        if ($interval < 1) {
            $interval = 1;
        } elseif ($interval > 1440) {
            $interval = 1440;
        }

        $lastSuccess = $this->fetchStatus->lastSuccessStartedAt();

        if ($lastSuccess === null) {
            return true;
        }

        return $lastSuccess->copy()->addMinutes($interval)->lte(now());
    }

    /**
     * Lock TTL must cover worst-case HTTP time:
     * (connect + read) * (retry_count + 1) + backoff + DB work.
     * Floor 120s so cPanel minute cron + withoutOverlapping(2) cannot overlap.
     */
    public function lockSeconds(): int
    {
        $connect = max(1, (int)config('gold.timeout_connect', 3));
        $read = max(1, (int)config('gold.timeout_read', 5));
        $attempts = max(1, (int)config('gold.retry_count', 1) + 1);
        $backoffMs = max(0, (int)config('gold.retry_backoff_milliseconds', 150));
        $httpBudget = ($connect + $read) * $attempts;
        $backoffBudget = (int)ceil(($backoffMs * $attempts * ($attempts + 1) / 2) / 1000);
        $dbBudget = 30;

        return max(120, $httpBudget + $backoffBudget + $dbBudget + 15);
    }
}
