<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FetchLog;
use App\Models\MarketItem;
use App\Models\PricePoint;
use App\Services\PriceIngestor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class MarketController extends Controller
{
    public function summary()
    {
        $ttl = max(5, (int)config('gold.summary_cache_seconds', 20));

        try {
            $payload = Cache::remember('gold:market-summary', $ttl, function () {
                $items = MarketItem::with('latestPrice')->where('is_active', true)->orderBy('category')->get();
                $lastFetch = FetchLog::latest('finished_at')->first();

                return [
                    'items' => $items->map(fn($item) => $this->itemResource($item)),
                    'lastFetch' => $this->fetchLogResource($lastFetch),
                    'config' => [
                        'sourceName' => config('gold.source_name'),
                        'sourceUrl' => config('gold.source_url'),
                        'chartDefaultRange' => $this->normalizeRange(config('gold.chart_default_range', '1d'))['key'],
                        'chartDefaultRangeDays' => config('gold.chart_default_range_days'),
                        'chartAvailableRanges' => config('gold.chart_available_ranges'),
                        'historyMaxDays' => config('gold.history_max_days'),
                        'chartMaxPoints' => config('gold.chart_max_points'),
                        'autoRefreshSeconds' => config('gold.frontend_refresh_seconds'),
                        'themeDefault' => config('gold.theme_default'),
                        'themeAccent' => config('gold.theme_accent'),
                        'features' => config('gold.features'),
                    ],
                ];
            });
        } catch (Throwable $exception) {
            Log::error('Market summary query failed', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            return $this->serverErrorResponse();
        }

        return response()->json($payload);
    }

    private function itemResource(MarketItem $item): array
    {
        $p = $this->displayPrice($item);
        return [
            'id' => $item->id,
            'name' => $item->name,
            'category' => $item->category,
            'currency' => $item->currency,
            'current' => $p?->current_value,
            'high' => $p?->high_value,
            'low' => $p?->low_value,
            'change' => $p?->change_value,
            'percent' => $p?->change_percent,
            'direction' => $p?->direction ?? 'none',
            'fetchedAt' => $p?->fetched_at?->toIso8601String(),
        ];
    }

    private function fetchLogResource(?FetchLog $log): ?array
    {
        if (!$log) {
            return null;
        }

        return [
            'status' => $log->status,
            'items_count' => $log->items_count,
            'started_at' => $log->started_at?->toIso8601String(),
            'finished_at' => $log->finished_at?->toIso8601String(),
        ];
    }

    private function displayPrice(MarketItem $item): ?PricePoint
    {
        $latest = $item->latestPrice;
        if ($this->isUsablePrice($latest?->current_value)) {
            return $latest;
        }

        return $item->prices()
            ->whereNotNull('current_value')
            ->where('current_value', '>', 0)
            ->latest('fetched_at')
            ->first();
    }

    public function history(Request $request, MarketItem $item)
    {
        $range = $this->normalizeRange($request->query('range') ?: $request->query('days') ?: config('gold.chart_default_range', '1d'));
        try {
            $points = PricePoint::where('market_item_id', $item->id)
                ->select(['current_value', 'high_value', 'low_value', 'change_value', 'change_percent', 'direction', 'fetched_at'])
                ->where('fetched_at', '>=', now()->subMinutes($range['minutes']))
                ->orderBy('fetched_at')
                ->get();
        } catch (Throwable $exception) {
            Log::error('Market history query failed', [
                'market_item_id' => $item->id,
                'range' => $range['key'],
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            return $this->serverErrorResponse();
        }
        $points = $this->repairHistoryPoints($points);
        $analytics = $this->analytics($points);
        $points = $this->samplePoints($points, (int)config('gold.chart_max_points', 600));
        return response()->json([
            'item' => $this->itemResource($item->load('latestPrice')),
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
            ]),
        ]);
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

    private function analytics($points): array
    {
        $values = $points->pluck('current_value')->filter(fn($value) => $this->isUsablePrice($value))->values();
        if ($values->isEmpty()) {
            return ['min' => null, 'max' => null, 'avg' => null, 'change' => null, 'changePercent' => null];
        }

        $first = (float)$values->first();
        $last = (float)$values->last();
        $change = $last - $first;

        return [
            'min' => (float)$values->min(),
            'max' => (float)$values->max(),
            'avg' => round((float)$values->avg(), 4),
            'change' => $change,
            'changePercent' => $first == 0.0 ? null : round(($change / $first) * 100, 4),
        ];
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

    private function repairHistoryPoints($points)
    {
        $rows = $points->values();

        return $rows->map(function ($point, $index) use ($rows) {
            if ($this->isUsablePrice($point->current_value)) {
                return $point;
            }

            $previous = $rows->slice(0, $index)->reverse()->first(fn($row) => $this->isUsablePrice($row->current_value));
            $next = $rows->slice($index + 1)->first(fn($row) => $this->isUsablePrice($row->current_value));

            if ($previous && $next) {
                $point->current_value = round(((float)$previous->current_value + (float)$next->current_value) / 2, 4);
                return $point;
            }

            return null;
        })->filter()->values();
    }

    private function isUsablePrice($value): bool
    {
        return $value !== null && is_numeric($value) && (float)$value > 0;
    }

    public function fetch(PriceIngestor $ingestor)
    {
        if (!config('gold.features.manual_fetch_api')) {
            return response()->json(['message' => 'دریافت دستی از API غیرفعال است.'], 403);
        }
        $token = (string)config('gold.manual_fetch_token');
        $provided = (string)request()->bearerToken();
        if ($token === '' || $provided === '' || !hash_equals($token, $provided)) {
            return response()->json(['message' => 'دسترسی غیرمجاز است.'], 401);
        }
        try {
            $result = $ingestor->fetchAndStore();
        } catch (Throwable $exception) {
            Log::error('Manual market fetch failed', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            return $this->serverErrorResponse();
        }

        return response()->json(['message' => 'داده‌ها به‌روزرسانی شد.', 'referenceId' => $result['referenceId'], 'items' => $result['items']]);
    }

    private function serverErrorResponse()
    {
        return response()->json([
            'message' => 'خطای داخلی سرور رخ داد. لطفاً کمی بعد دوباره تلاش کنید.',
        ], 500);
    }
}
