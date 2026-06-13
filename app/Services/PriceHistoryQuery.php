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

    public function latestFetchedAt(int $marketItemId): ?Carbon
    {
        $value = PricePoint::where('market_item_id', $marketItemId)->max('fetched_at');

        return $value ? Carbon::parse($value) : null;
    }

    public function fetchChartPoints(int $marketItemId, Carbon $windowStart, int $rangeMinutes, int $maxPoints): Collection
    {
        $maxPoints = max(20, $maxPoints);
        $threshold = max(60, (int)config('gold.chart_sql_bucket_threshold_minutes', 360));

        if ($rangeMinutes <= $threshold) {
            return $this->fetchRawPoints($marketItemId, $windowStart);
        }

        return $this->fetchBucketedPoints($marketItemId, $windowStart, $rangeMinutes, $maxPoints);
    }

    private function fetchRawPoints(int $marketItemId, Carbon $windowStart): Collection
    {
        return PricePoint::query()
            ->where('market_item_id', $marketItemId)
            ->select(self::SELECT_COLUMNS)
            ->where('fetched_at', '>=', $windowStart)
            ->where('current_value', '>', 0)
            ->orderBy('fetched_at')
            ->get();
    }

    private function fetchBucketedPoints(int $marketItemId, Carbon $windowStart, int $rangeMinutes, int $maxPoints): Collection
    {
        $bucketSeconds = max(60, (int)ceil(($rangeMinutes * 60) / $maxPoints));

        $bucketQuery = DB::table('price_points')
            ->selectRaw('MAX(fetched_at) as bucket_fetched_at')
            ->where('market_item_id', $marketItemId)
            ->where('fetched_at', '>=', $windowStart)
            ->where('current_value', '>', 0)
            ->groupByRaw('FLOOR(UNIX_TIMESTAMP(fetched_at) / ' . $bucketSeconds . ')');

        return PricePoint::query()
            ->joinSub($bucketQuery, 'buckets', function ($join) {
                $join->on('price_points.fetched_at', '=', 'buckets.bucket_fetched_at');
            })
            ->where('price_points.market_item_id', $marketItemId)
            ->select(array_map(fn($column) => "price_points.{$column}", self::SELECT_COLUMNS))
            ->orderBy('price_points.fetched_at')
            ->get();
    }

    public function fetchAnalytics(int $marketItemId, Carbon $windowStart, Collection $points, bool $fromFullRange): array
    {
        if ($fromFullRange) {
            return $this->analyticsFromSql($marketItemId, $windowStart);
        }

        return $this->analyticsFromPoints($points);
    }

    private function analyticsFromSql(int $marketItemId, Carbon $windowStart): array
    {
        $row = PricePoint::query()
            ->where('market_item_id', $marketItemId)
            ->where('fetched_at', '>=', $windowStart)
            ->where('current_value', '>', 0)
            ->selectRaw('
                MIN(current_value) as min_val,
                MAX(current_value) as max_val,
                AVG(current_value) as avg_val,
                SUBSTRING_INDEX(GROUP_CONCAT(current_value ORDER BY fetched_at ASC), ",", 1) as first_val,
                SUBSTRING_INDEX(GROUP_CONCAT(current_value ORDER BY fetched_at DESC), ",", 1) as last_val
            ')
            ->first();

        if ($row === null || $row->min_val === null) {
            return ['min' => null, 'max' => null, 'avg' => null, 'change' => null, 'changePercent' => null];
        }

        $first = (float)$row->first_val;
        $last = (float)$row->last_val;
        $change = $last - $first;

        return [
            'min' => (float)$row->min_val,
            'max' => (float)$row->max_val,
            'avg' => round((float)$row->avg_val, 4),
            'change' => $change,
            'changePercent' => $first == 0.0 ? null : round(($change / $first) * 100, 4),
        ];
    }

    private function analyticsFromPoints(Collection $points): array
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
