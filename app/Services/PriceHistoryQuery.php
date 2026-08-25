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

    /**
     * @param Collection $points Filtered series (min/max/avg).
     * @param float|null $open Window-open anchor (near windowStart); defaults to first point.
     * @param float|null $close Window-close anchor (latest valid); defaults to last point.
     */
    public function fetchAnalytics(Collection $points, ?float $open = null, ?float $close = null): array
    {
        $values = $points->pluck('current_value')->filter(fn($value) => $this->isUsablePrice($value))->values();
        if ($values->isEmpty() && $open === null && $close === null) {
            return ['min' => null, 'max' => null, 'avg' => null, 'change' => null, 'changePercent' => null];
        }

        $first = $open ?? ($values->isEmpty() ? null : (float)$values->first());
        $last = $close ?? ($values->isEmpty() ? null : (float)$values->last());
        $change = ($first !== null && $last !== null) ? ($last - $first) : null;

        return [
            'min' => $values->isEmpty() ? null : (float)$values->min(),
            'max' => $values->isEmpty() ? null : (float)$values->max(),
            'avg' => $values->isEmpty() ? null : round((float)$values->avg(), 4),
            'change' => $change,
            'changePercent' => ($first === null || $first == 0.0 || $change === null)
                ? null
                : round(($change / $first) * 100, 4),
        ];
    }

    private function isUsablePrice($value): bool
    {
        return $value !== null && is_numeric($value) && (float)$value > 0;
    }

    /**
     * Nearest usable price to window start (open) and last usable (close), before sampling.
     *
     * @return array{0: ?float, 1: ?float}
     */
    public function windowAnchors(Collection $points, Carbon $windowStart): array
    {
        $usable = $points
            ->filter(fn($point) => $this->isUsablePrice($point->current_value ?? null))
            ->values();

        if ($usable->isEmpty()) {
            return [null, null];
        }

        $open = $usable
            ->sortBy(function ($point) use ($windowStart) {
                $at = $point->fetched_at;
                if (!$at) {
                    return PHP_INT_MAX;
                }

                return abs($at->diffInSeconds($windowStart));
            })
            ->first();

        $close = $usable->last();

        return [
            $open ? (float)$open->current_value : null,
            $close ? (float)$close->current_value : null,
        ];
    }
}
