<?php

namespace App\Services;

use App\Models\FetchLog;

class AutoPriceFetcher
{
    public function __construct(private PriceIngestor $ingestor)
    {
    }

    public function fetchIfDue(bool $force = false): ?array
    {
        try {
            if (!$force && !$this->isDue()) {
                return null;
            }

            return $this->ingestor->fetchAndStore();
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    public function isDue(): bool
    {
        $interval = max(1, (int)config('gold.fetch_interval_minutes', 5));
        $now = now();

        if ($now->minute % $interval !== 0) {
            return false;
        }

        $slotStart = $now->copy()->second(0);
        $lastSuccess = FetchLog::where('status', 'success')
            ->whereNotNull('started_at')
            ->latest('started_at')
            ->first();

        if ($lastSuccess && $lastSuccess->started_at && $lastSuccess->started_at->gte($slotStart)) {
            return false;
        }

        return true;
    }
}
