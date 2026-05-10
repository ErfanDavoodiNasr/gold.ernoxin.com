<?php

namespace App\Services;

use App\Models\FetchLog;
use Illuminate\Support\Facades\Cache;

class AutoPriceFetcher
{
    public function __construct(private PriceIngestor $ingestor)
    {
    }

    public function fetchIfDue(bool $force = false): ?array
    {
        if (!config('gold.features.auto_fetch')) {
            return null;
        }

        $ttl = max(60, ((int)config('gold.fetch_lock_seconds', 120)));
        $lock = Cache::lock('gold:price-fetch', $ttl);

        if (!$lock->get()) {
            return null;
        }

        try {
            if (!$force && !$this->isDue()) {
                return null;
            }

            return $this->ingestor->fetchAndStore();
        } catch (\Throwable $e) {
            report($e);
            return null;
        } finally {
            optional($lock)->release();
        }
    }

    public function isDue(): bool
    {
        $interval = max(1, (int)config('gold.fetch_interval_minutes', 5));
        $lastSuccess = FetchLog::where('status', 'success')->latest('finished_at')->first();

        if ($lastSuccess && $lastSuccess->finished_at && $lastSuccess->finished_at->gt(now()->subMinutes($interval))) {
            return false;
        }

        return !FetchLog::where('status', 'running')
            ->where('started_at', '>=', now()->subMinutes($interval))
            ->exists();
    }
}
