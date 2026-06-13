<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketItem;
use App\Services\MarketSummaryService;
use App\Services\PriceHistoryQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class MarketController extends Controller
{
    public function __construct(
        private PriceHistoryQuery    $historyQuery,
        private MarketSummaryService $summaryService,
    )
    {
    }

    public function summary()
    {
        $ttl = max(5, (int)config('gold.summary_cache_seconds', 20));

        try {
            $payload = $this->summaryService->apiPayload();
        } catch (Throwable $exception) {
            Log::error('Market summary query failed', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            return $this->serverErrorResponse();
        }

        return $this->cachedJson($payload, $ttl);
    }

    private function serverErrorResponse()
    {
        return response()->json([
            'message' => 'خطای داخلی سرور رخ داد. لطفاً کمی بعد دوباره تلاش کنید.',
        ], 500);
    }

    private function cachedJson($payload, int $ttl)
    {
        $response = response()->json($payload);
        $etag = '"' . sha1((string)$response->getContent()) . '"';
        $headers = [
            'Cache-Control' => "public, max-age={$ttl}, s-maxage={$ttl}, stale-while-revalidate=" . ($ttl * 6),
            'ETag' => $etag,
        ];

        if (request()->headers->get('If-None-Match') === $etag) {
            return response('', 304, $headers);
        }

        return $response->withHeaders($headers);
    }

    public function history(Request $request, MarketItem $item)
    {
        $range = $this->normalizeRange($request->query('range') ?: $request->query('days') ?: config('gold.chart_default_range', '1d'));
        $ttl = $this->historyCacheTtl($range);

        try {
            $latestFetchedAtKey = $this->cachedLatestFetchedAt($item->id);
            $cacheKey = implode(':', [
                'gold',
                'market-history',
                'v5',
                $item->getKey(),
                $range['key'],
                md5($latestFetchedAtKey),
            ]);

            $payload = Cache::remember($cacheKey, $ttl, function () use ($item, $range, $latestFetchedAtKey) {
                $latestFetchedAt = $latestFetchedAtKey === 'empty'
                    ? null
                    : \Illuminate\Support\Carbon::parse($latestFetchedAtKey);

                if (!$latestFetchedAt) {
                    return [
                        'range' => $range['key'],
                        'analytics' => ['min' => null, 'max' => null, 'avg' => null, 'change' => null, 'changePercent' => null],
                        'points' => [],
                    ];
                }

                $windowStart = $latestFetchedAt->copy()->subMinutes($range['minutes']);
                $maxPoints = (int)config('gold.chart_max_points', 600);
                $useSqlBuckets = $range['minutes'] > max(60, (int)config('gold.chart_sql_bucket_threshold_minutes', 360));
                $points = $this->historyQuery->fetchChartPoints($item->id, $windowStart, $range['minutes'], $maxPoints);

                if (!$useSqlBuckets) {
                    $points = $this->filterHistoryOutliers($points);
                    $points = $this->samplePoints($points, $maxPoints);
                }

                $analytics = $this->historyQuery->fetchAnalytics($item->id, $windowStart, $points, $useSqlBuckets);

                return [
                    'range' => $range['key'],
                    'analytics' => $analytics,
                    'points' => $points->map(fn($p) => [
                        'time' => optional($p->fetched_at)->toIso8601String(),
                        'current' => $p->current_value,
                        'high' => $p->high_value,
                        'low' => $p->low_value,
                        'change' => $p->change_value,
                        'percent' => $p->change_percent,
                        'direction' => $p->direction,
                    ])->values(),
                ];
            });
        } catch (Throwable $exception) {
            Log::error('Market history query failed', [
                'market_item_id' => $item->id,
                'range' => $range['key'],
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            return $this->serverErrorResponse();
        }

        return $this->cachedJson($payload, $ttl);
    }

    private function normalizeRange($value): array
    {
        $historyMaxDays = max(1, (int)config('gold.history_max_days', 365));
        $raw = strtolower(trim((string)$value));

        if (preg_match('/^(\d+)\s*([hd])$/', $raw, $matches)) {
            $amount = max(1, (int)$matches[1]);
            $unit = $matches[2];
        } else {
            $amount = max(1, (int)$raw);
            $unit = 'd';
        }

        $minutes = $unit === 'h' ? $amount * 60 : $amount * 1440;
        $maxMinutes = $historyMaxDays * 1440;
        $minutes = min($minutes, $maxMinutes);

        return [
            'key' => $unit === 'h' ? "{$amount}h" : "{$amount}d",
            'minutes' => $minutes,
        ];
    }

    private function historyCacheTtl(array $range): int
    {
        $minutes = $range['minutes'];

        if ($minutes >= 43200) {
            return max(60, (int)config('gold.history_cache_seconds_long', 300));
        }

        if ($minutes >= 10080) {
            return max(30, (int)config('gold.history_cache_seconds_medium', 120));
        }

        return max(10, (int)config('gold.history_cache_seconds', 45));
    }

    private function cachedLatestFetchedAt(int $marketItemId): string
    {
        $version = (int)Cache::get('gold:price-data-version', 0);

        return (string)Cache::remember(
            "gold:item-latest-fetch:{$marketItemId}:v{$version}",
            max(5, (int)config('gold.latest_fetch_cache_seconds', 10)),
            fn() => $this->historyQuery->latestFetchedAt($marketItemId)?->toIso8601String() ?: 'empty'
        );
    }

    private function filterHistoryOutliers($points)
    {
        $reference = null;

        return $points->values()->filter(function ($point) use (&$reference) {
            if (!$this->isUsablePrice($point->current_value)) {
                return false;
            }

            $value = (float)$point->current_value;
            if ($reference !== null) {
                $ratio = $value / $reference;
                if ($ratio >= 8 && $ratio <= 12) {
                    return false;
                }
                if ($ratio >= 0.08 && $ratio <= 0.12) {
                    return false;
                }
            }

            $reference = $value;

            return true;
        })->values();
    }

    private function isUsablePrice($value): bool
    {
        return $value !== null && is_numeric($value) && (float)$value > 0;
    }

    private function samplePoints($points, int $maxPoints)
    {
        $maxPoints = max(20, $maxPoints);
        if ($points->count() <= $maxPoints) {
            return $points->values();
        }

        $lastIndex = $points->count() - 1;
        $stride = (int)ceil($points->count() / $maxPoints);

        return $points
            ->filter(fn($point, $index) => $index % $stride === 0 || $index === $lastIndex)
            ->values();
    }
}
