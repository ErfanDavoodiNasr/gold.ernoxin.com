<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MarketSummaryService;
use App\Services\OutlierFilter;
use App\Services\PersianNumber;
use App\Services\PriceHistoryQuery;
use App\Services\RangeParser;
use App\Support\MarketItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class MarketController extends Controller
{
    public function __construct(
        private PriceHistoryQuery    $historyQuery,
        private MarketSummaryService $summaryService,
        private RangeParser          $rangeParser,
        private OutlierFilter        $outlierFilter,
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
        $requestedRange = $request->query('range') ?: $request->query('days') ?: config('gold.chart_default_range', '1d');
        $range = $this->rangeParser->tryParse($requestedRange);
        if ($range === null) {
            return response()->json([
                'message' => 'بازهٔ نامعتبر است.',
                'requestedRange' => (string)$requestedRange,
                'allowed' => config('gold.chart_available_ranges'),
            ], 422);
        }

        $ttl = $this->historyCacheTtl($range);

        try {
            $latestFetchedAtKey = $this->cachedLatestFetchedAt($item->key);
            $cacheKey = implode(':', [
                'gold',
                'market-history',
                'v11',
                $item->key,
                $range['key'],
                md5($latestFetchedAtKey),
            ]);

            $payload = Cache::remember($cacheKey, $ttl, function () use ($item, $range, $latestFetchedAtKey) {
                if ($latestFetchedAtKey === 'empty') {
                    return [
                        'range' => $range['key'],
                        'anchor' => 'now',
                        'analytics' => ['min' => null, 'max' => null, 'avg' => null, 'change' => null, 'changePercent' => null],
                        'points' => [],
                    ];
                }

                $windowStart = now()->subMinutes($range['minutes']);
                $maxPoints = (int)config('gold.chart_max_points', 600);
                $useSqlBuckets = $range['minutes'] > max(60, (int)config('gold.chart_sql_bucket_threshold_minutes', 360));

                $points = $this->historyQuery->fetchChartPoints($item->key, $windowStart, $range['minutes'], $maxPoints);
                $points = $this->filterHistoryOutliers($points);

                // Open/close of the window — before sample. Sample is draw-only.
                [$open, $close] = $this->historyQuery->windowAnchors($points, $windowStart);
                $analytics = $this->historyQuery->fetchAnalytics($points, $open, $close);

                if (!$useSqlBuckets) {
                    $points = $this->samplePoints($points, $maxPoints);
                }

                return [
                    'range' => $range['key'],
                    'anchor' => 'now',
                    'analytics' => $analytics,
                    'points' => $points->map(function ($p) {
                        $direction = $p->direction ?? 'none';

                        return [
                            'time' => optional($p->fetched_at)->toIso8601String(),
                            'current' => $p->current_value,
                            'high' => $p->high_value,
                            'low' => $p->low_value,
                            'change' => PersianNumber::signedByDirection($p->change_value, $direction),
                            'percent' => PersianNumber::signedByDirection($p->change_percent, $direction),
                            'direction' => $direction,
                        ];
                    })->values(),
                ];
            });
        } catch (Throwable $exception) {
            Log::error('Market history query failed', [
                'item_key' => $item->key,
                'range' => $range['key'],
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            return $this->serverErrorResponse();
        }

        return $this->cachedJson($payload, $ttl);
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

    private function cachedLatestFetchedAt(string $itemKey): string
    {
        $version = (int)Cache::get('gold:price-data-version', 0);

        return (string)Cache::remember(
            'gold:item-latest-fetch:' . md5($itemKey) . ":v{$version}",
            max(5, (int)config('gold.latest_fetch_cache_seconds', 10)),
            fn() => $this->historyQuery->latestFetchedAt($itemKey)?->toIso8601String() ?: 'empty'
        );
    }

    private function filterHistoryOutliers($points)
    {
        return $this->outlierFilter->filter(
            $points,
            fn($point) => $point->current_value,
        );
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
