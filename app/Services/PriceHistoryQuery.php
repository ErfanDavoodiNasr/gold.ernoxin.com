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
        'fetched_at',
    ];

    public function fetchChartPoints(string $itemKey, Carbon $windowStart, int $rangeMinutes, int $maxPoints): Collection
    {
        // Always aggregate in SQL — short ranges used to load every raw row then sample in PHP.
        return $this->fetchBucketedPoints($itemKey, $windowStart, $rangeMinutes, max(20, $maxPoints));
    }

    private function fetchBucketedPoints(string $itemKey, Carbon $windowStart, int $rangeMinutes, int $maxPoints): Collection
    {
        $bucketSeconds = max(60, (int)ceil(($rangeMinutes * 60) / $maxPoints));

        $bucketQuery = DB::table('price_points')
            ->selectRaw('item_key, MAX(fetched_at) as bucket_fetched_at')
            ->where('item_key', $itemKey)
            ->where('fetched_at', '>=', $windowStart)
            ->where('current_value', '>', 0)
            ->groupByRaw('item_key, FLOOR(UNIX_TIMESTAMP(fetched_at) / ' . $bucketSeconds . ')');

        return PricePoint::query()
            ->joinSub($bucketQuery, 'buckets', function ($join) {
                $join->on('price_points.item_key', '=', 'buckets.item_key')
                    ->on('price_points.fetched_at', '=', 'buckets.bucket_fetched_at');
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
     * First / last usable price in an already time-ordered series (open / close).
     *
     * @return array{0: ?float, 1: ?float}
     */
    public function windowAnchors(Collection $points, Carbon $windowStart): array
    {
        $open = null;
        $close = null;

        foreach ($points as $point) {
            if (!$this->isUsablePrice($point->current_value ?? null)) {
                continue;
            }

            $value = (float)$point->current_value;
            if ($open === null) {
                $open = $value;
            }
            $close = $value;
        }

        return [$open, $close];
    }
}
