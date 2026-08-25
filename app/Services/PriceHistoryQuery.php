<?php

namespace App\Services;

use App\Models\PricePoint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PriceHistoryQuery
{
    private const SELECT_COLUMNS = [
        'current_value',
        'high_value',
        'low_value',
        'change_value',
        'change_percent',
        'direction',
        'fetched_at',
    ];

    public function latestFetchedAt(string $itemKey): ?Carbon
    {
        $value = PricePoint::where('item_key', $itemKey)->max('fetched_at');

        return $value ? Carbon::parse($value) : null;
    }

    public function fetchChartPoints(string $itemKey, Carbon $windowStart, int $rangeMinutes, int $maxPoints): Collection
    {
        $maxPoints = max(20, $maxPoints);
        $threshold = max(60, (int)config('gold.chart_sql_bucket_threshold_minutes', 360));

        if ($rangeMinutes <= $threshold) {
            return $this->fetchRawPoints($itemKey, $windowStart);
        }

        return $this->fetchBucketedPoints($itemKey, $windowStart, $rangeMinutes, $maxPoints);
    }

    private function fetchRawPoints(string $itemKey, Carbon $windowStart): Collection
    {
        return PricePoint::query()
            ->where('item_key', $itemKey)
            ->select(self::SELECT_COLUMNS)
            ->where('fetched_at', '>=', $windowStart)
            ->where('current_value', '>', 0)
            ->orderBy('fetched_at')
            ->get();
    }

    private function fetchBucketedPoints(string $itemKey, Carbon $windowStart, int $rangeMinutes, int $maxPoints): Collection
    {
        $bucketSeconds = max(60, (int)ceil(($rangeMinutes * 60) / $maxPoints));

        $bucketQuery = DB::table('price_points')
            ->selectRaw('MAX(fetched_at) as bucket_fetched_at')
            ->where('item_key', $itemKey)
            ->where('fetched_at', '>=', $windowStart)
            ->where('current_value', '>', 0)
            ->groupByRaw('FLOOR(UNIX_TIMESTAMP(fetched_at) / ' . $bucketSeconds . ')');

        return PricePoint::query()
            ->joinSub($bucketQuery, 'buckets', function ($join) {
                $join->on('price_points.fetched_at', '=', 'buckets.bucket_fetched_at');
            })
            ->where('price_points.item_key', $itemKey)
            ->select(array_map(fn($column) => "price_points.{$column}", self::SELECT_COLUMNS))
            ->orderBy('price_points.fetched_at')
            ->get();
    }

    public function fetchAnalytics(Collection $points): array
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

    private function isUsablePrice($value): bool
    {
        return $value !== null && is_numeric($value) && (float)$value > 0;
    }
}
