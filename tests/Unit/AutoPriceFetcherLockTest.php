<?php

namespace Tests\Unit;

use App\Services\AutoPriceFetcher;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AutoPriceFetcherLockTest extends TestCase
{
    public function test_lock_seconds_covers_http_budget(): void
    {
        config([
            'gold.timeout_connect' => 3,
            'gold.timeout_read' => 5,
            'gold.retry_count' => 1,
            'gold.retry_backoff_milliseconds' => 150,
        ]);

        $fetcher = app(AutoPriceFetcher::class);
        // (3+5)*(1+1)=16 + backoff + db + margin, floored at 120
        $this->assertGreaterThanOrEqual(120, $fetcher->lockSeconds());

        config([
            'gold.timeout_connect' => 30,
            'gold.timeout_read' => 60,
            'gold.retry_count' => 3,
            'gold.retry_backoff_milliseconds' => 1000,
        ]);
        $fetcher = app(AutoPriceFetcher::class);
        // (30+60)*4 = 360 + backoff(~10) + 30 + 15 > 120
        $this->assertGreaterThanOrEqual(360, $fetcher->lockSeconds());
    }

    public function test_interval_clamps_invalid_values(): void
    {
        // Avoid DB fallback — seed a successful fetch inside the interval window.
        Cache::put('gold:last-fetch', [
            'status' => 'success',
            'items_count' => 9,
            'started_at' => now()->subSeconds(30)->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'message' => null,
        ], now()->addHour());

        $fetcher = app(AutoPriceFetcher::class);

        config(['gold.fetch_interval_minutes' => 0]);
        // Clamped to 1 minute; last success 30s ago → not due.
        $this->assertFalse($fetcher->isDue());

        config(['gold.fetch_interval_minutes' => -5]);
        $this->assertFalse($fetcher->isDue());

        Cache::put('gold:last-fetch', [
            'status' => 'success',
            'items_count' => 9,
            'started_at' => now()->subDays(2)->toIso8601String(),
            'finished_at' => now()->subDays(2)->toIso8601String(),
            'message' => null,
        ], now()->addHour());

        config(['gold.fetch_interval_minutes' => 99999]);
        // Clamped to 1440 minutes; 2 days ago → due.
        $this->assertTrue($fetcher->isDue());
    }
}
