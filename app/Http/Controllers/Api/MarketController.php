<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MarketSummaryService;
use App\Services\OutlierFilter;
use App\Services\PriceHistoryQuery;
use App\Services\RangeParser;
use App\Support\MarketItem;
use App\Support\StampedeCache;
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

        $version = (int)Cache::get('gold:price-data-version', 0);

        return $this->cachedJson($payload, $ttl, "summary:{$version}");
    }

    private function serverErrorResponse()
    {
        return response()->json([
            'message' => 'خطای داخلی سرور رخ داد. لطفاً کمی بعد دوباره تلاش کنید.',
        ], 500);
    }

    private function cachedJson($payload, int $ttl, string $etagSeed)
    {
        $etag = '"' . sha1($etagSeed) . '"';
        $headers = [
            'Cache-Control' => "public, max-age={$ttl}, s-maxage={$ttl}, stale-while-revalidate=" . ($ttl * 6),
            'ETag' => $etag,
        ];

        if (request()->headers->get('If-None-Match') === $etag) {
            return response('', 304, $headers);
        }

        return response()->json($payload)->withHeaders($headers);
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
            // Stable key — invalidated explicitly on ingest (PriceIngestor::clearCaches).
            $cacheKey = "gold:market-history:{$item->key}:{$range['key']}";

            $payload = StampedeCache::remember($cacheKey, $ttl, function () use ($item, $range) {
                $windowStart = now()->subMinutes($range['minutes']);
                $maxPoints = (int)config('gold.chart_max_points', 600);

                $points = $this->historyQuery->fetchChartPoints($item->key, $windowStart, $range['minutes'], $maxPoints);
                $points = $this->filterHistoryOutliers($points);

                [$open, $close] = $this->historyQuery->windowAnchors($points, $windowStart);
                $analytics = $this->historyQuery->fetchAnalytics($points, $open, $close);

                return [
                    'range' => $range['key'],
                    'anchor' => 'now',
                    'analytics' => $analytics,
                    'points' => $points->map(fn($p) => [
                        'time' => optional($p->fetched_at)->toIso8601String(),
                        'current' => $p->current_value,
                    ])->values(),
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

        $version = (int)Cache::get('gold:price-data-version', 0);

        return $this->cachedJson($payload, $ttl, "{$version}:{$item->key}:{$range['key']}");
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

    private function filterHistoryOutliers($points)
    {
        return $this->outlierFilter->filter(
            $points,
            fn($point) => $point->current_value,
        );
    }
}
