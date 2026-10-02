<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PriceHistoryQuery
{
    /** Ranges ≥7d read hourly rollups instead of every raw ingest point. */
    private const HOURLY_RANGE_MINUTES = 10080;

    public function fetchChartPoints(string $itemKey, Carbon $windowStart, int $rangeMinutes, int $maxPoints): Collection
    {
        $maxPoints = max(20, $maxPoints);

        if ($rangeMinutes >= self::HOURLY_RANGE_MINUTES && Schema::hasTable('price_points_hourly')) {
            $points = $this->fetchBucketedFromTable(
                'price_points_hourly',
                'bucket_at',
                $itemKey,
                $windowStart,
                $rangeMinutes,
                $maxPoints,
                3600,
            );
            if ($points->isNotEmpty()) {
                return $this->finalizeChartPoints($points, $rangeMinutes, $maxPoints);
            }
        }

        $points = $this->fetchBucketedFromTable(
            'price_points',
            'fetched_at',
            $itemKey,
            $windowStart,
            $rangeMinutes,
            $maxPoints,
            60,
        );

        return $this->finalizeChartPoints($points, $rangeMinutes, $maxPoints);
    }

    /**
     * Single-pass bucket: last usable value per time bucket (no self-join).
     */
    private function fetchBucketedFromTable(
        string $table,
        string $timeColumn,
        string $itemKey,
        Carbon $windowStart,
        int    $rangeMinutes,
        int    $maxPoints,
        int    $minBucketSeconds,
    ): Collection
    {
        // maxPoints - 1 intervals between endpoints — avoids an extra boundary bucket.
        $spanSeconds = $rangeMinutes * 60;
        $bucketSeconds = max($minBucketSeconds, (int)ceil($spanSeconds / max(1, $maxPoints - 1)));

        $bucketExpr = 'FLOOR(UNIX_TIMESTAMP(' . $timeColumn . ') / ' . $bucketSeconds . ')';

        // ROW_NUMBER avoids GROUP_CONCAT truncation (group_concat_max_len) on large prices.
        // Allowlist table/column names — never interpolate request input here.
        $allowed = [
            'price_points' => 'fetched_at',
            'price_points_hourly' => 'bucket_at',
        ];
        if (($allowed[$table] ?? null) !== $timeColumn) {
            throw new \InvalidArgumentException('Invalid history table/column pair.');
        }

        $rows = DB::select(
            'SELECT ' . $timeColumn . ' AS fetched_at, current_value
             FROM (
                 SELECT ' . $timeColumn . ', current_value,
                        ROW_NUMBER() OVER (
                            PARTITION BY ' . $bucketExpr . '
                            ORDER BY ' . $timeColumn . ' DESC
                        ) AS rn
                 FROM ' . $table . '
                 WHERE item_key = ?
                   AND ' . $timeColumn . ' >= ?
                   AND current_value > 0
             ) ranked
             WHERE rn = 1
             ORDER BY ' . $timeColumn,
            [$itemKey, $windowStart],
        );

        // DB::select returns a plain array — wrap before Collection methods.
        return collect($rows)->map(fn($row) => (object)[
            'fetched_at' => Carbon::parse($row->fetched_at),
            'current_value' => (float)$row->current_value,
        ]);
    }

    private function finalizeChartPoints(Collection $points, int $rangeMinutes, int $maxPoints): Collection
    {
        if ($rangeMinutes < self::HOURLY_RANGE_MINUTES) {
            $points = $this->dedupConsecutiveEqual($points);
        }

        if ($points->count() > $maxPoints) {
            $points = $points->slice(-$maxPoints)->values();
        }

        return $points;
    }

    /** Drop flat runs so short-range charts stay light after bucketing. */
    private function dedupConsecutiveEqual(Collection $points): Collection
    {
        $result = collect();
        $previousValue = null;

        foreach ($points as $point) {
            $value = (float)$point->current_value;
            if ($previousValue !== null && $value === $previousValue) {
                continue;
            }

            $result->push($point);
            $previousValue = $value;
        }

        return $result;
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
