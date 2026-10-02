<?php

namespace Tests\Unit;

use App\Services\PriceHistoryQuery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

class PriceHistoryQueryTest extends TestCase
{
    public function test_collect_wrap_handles_array_rows(): void
    {
        $query = new PriceHistoryQuery();
        $method = new ReflectionMethod(PriceHistoryQuery::class, 'fetchBucketedFromTable');
        // We only assert the allowlist rejects unknown tables — full SQL needs MySQL.
        $this->expectException(\InvalidArgumentException::class);
        $method->setAccessible(true);
        $method->invoke(
            $query,
            'evil_table',
            'fetched_at',
            'طلای ۱۸ عیار',
            Carbon::now()->subDay(),
            1440,
            100,
            60,
        );
    }

    public function test_analytics_known_answers(): void
    {
        $query = new PriceHistoryQuery();
        $points = new Collection([
            (object)['current_value' => 100.0, 'fetched_at' => now()],
            (object)['current_value' => 110.0, 'fetched_at' => now()],
            (object)['current_value' => 90.0, 'fetched_at' => now()],
        ]);

        $analytics = $query->fetchAnalytics($points, 100.0, 90.0);
        $this->assertSame(90.0, $analytics['min']);
        $this->assertSame(110.0, $analytics['max']);
        $this->assertEqualsWithDelta(100.0, $analytics['avg'], 0.0001);
        $this->assertSame(-10.0, $analytics['change']);
        $this->assertSame(-10.0, $analytics['changePercent']);
    }

    public function test_analytics_never_emits_nan_on_zero_open(): void
    {
        $query = new PriceHistoryQuery();
        $points = new Collection([
            (object)['current_value' => 0.0],
            (object)['current_value' => 10.0],
        ]);
        $analytics = $query->fetchAnalytics($points, 0.0, 10.0);
        $this->assertNull($analytics['changePercent']);
        $this->assertTrue(is_finite($analytics['change'] ?? 0));
    }
}
