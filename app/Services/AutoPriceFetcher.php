<?php

namespace App\Services;

use App\Models\FetchLog;

class AutoPriceFetcher
{
    public function __construct(private PriceIngestor $ingestor)
    {
    }

    public function fetchIfDue(): void
    {
        if (!config('gold.features.auto_fetch')) {
            return;
        }

        $interval = max(1, (int)config('gold.fetch_interval_minutes', 5));
        $lastSuccess = FetchLog::where('status', 'success')->latest('finished_at')->first();

        if ($lastSuccess && $lastSuccess->finished_at && $lastSuccess->finished_at->gt(now()->subMinutes($interval))) {
            return;
        }

        $recentRunning = FetchLog::where('status', 'running')
            ->where('started_at', '>=', now()->subMinutes($interval))
            ->exists();

        if ($recentRunning) {
            return;
        }

        try {
            $this->ingestor->fetchAndStore();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
