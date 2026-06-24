<?php

namespace App\Services;

use App\Models\PricePoint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ImpliedDollarService
{
    public function __construct(private ImpliedDollarCalculator $calculator)
    {
    }

    public function latestPricePoint(): ?PricePoint
    {
        $rows = $this->fetchJoinedRows(null, 2);
        if ($rows->isEmpty()) {
            return null;
        }

        $current = $this->rowToPricePoint($rows->last());
        $previous = $rows->count() > 1 ? $this->rowToPricePoint($rows->get($rows->count() - 2)) : null;
        $this->applyChange($current, $previous?->current_value);

        return $current;
    }

    private function fetchJoinedRows(?Carbon $windowStart, ?int $limit = null): Collection
    {
        $goldKey = $this->calculator->gold18Key();
        $ounceKey = $this->calculator->ounceKey();

        $query = DB::table('price_points as g')
            ->join('price_points as o', function ($join) {
                $join->on('g.fetched_at', '=', 'o.fetched_at');
            })
            ->where('g.item_key', $goldKey)
            ->where('o.item_key', $ounceKey)
            ->where('g.current_value', '>', 0)
            ->where('o.current_value', '>', 0)
            ->select([
                'g.fetched_at',
                'g.current_value as gold18_current',
                'g.high_value as gold18_high',
                'g.low_value as gold18_low',
                'o.current_value as ounce_current',
                'o.high_value as ounce_high',
                'o.low_value as ounce_low',
            ]);

        if ($windowStart) {
            $query->where('g.fetched_at', '>=', $windowStart);
        }

        if (!$windowStart && $limit !== null) {
            return $query
                ->orderByDesc('g.fetched_at')
                ->limit($limit)
                ->get()
                ->sortBy('fetched_at')
                ->values();
        }

        return $query->orderBy('g.fetched_at')->get();
    }

    private function rowToPricePoint(object $row): PricePoint
    {
        $current = $this->calculator->compute(
            (float)$row->gold18_current,
            (float)$row->ounce_current
        );
        $range = $this->calculator->computeRange(
            (float)$row->gold18_high,
            (float)$row->gold18_low,
            (float)$row->ounce_high,
            (float)$row->ounce_low
        );

        return new PricePoint([
            'item_key' => PersianNumber::label(config('gold.implied_dollar.key', 'دلار محاسباتی')),
            'current_value' => $current,
            'high_value' => $range['high'],
            'low_value' => $range['low'],
            'fetched_at' => Carbon::parse($row->fetched_at),
            'direction' => 'none',
            'change_value' => null,
            'change_percent' => null,
        ]);
    }

    private function applyChange(PricePoint $point, ?float $previousValue): void
    {
        $current = $point->current_value;
        if ($current === null || $previousValue === null || $previousValue <= 0) {
            return;
        }

        $change = $current - $previousValue;
        $point->change_value = round($change, 4);
        $point->change_percent = round(($change / $previousValue) * 100, 4);
        $point->direction = $change > 0 ? 'asc' : ($change < 0 ? 'desc' : 'none');
    }

    public function latestFetchedAt(): ?Carbon
    {
        $row = $this->fetchJoinedRows(null, 1)->last();

        return $row ? Carbon::parse($row->fetched_at) : null;
    }

    public function fetchChartPoints(Carbon $windowStart, int $rangeMinutes, int $maxPoints): Collection
    {
        $maxPoints = max(20, $maxPoints);
        $threshold = max(60, (int)config('gold.chart_sql_bucket_threshold_minutes', 360));

        if ($rangeMinutes <= $threshold) {
            return $this->mapRowsToPoints($this->fetchJoinedRows($windowStart));
        }

        return $this->fetchBucketedPoints($windowStart, $rangeMinutes, $maxPoints);
    }

    private function mapRowsToPoints(Collection $rows): Collection
    {
        $points = collect();
        $previousValue = null;

        foreach ($rows as $row) {
            $point = $this->rowToPricePoint($row);
            $this->applyChange($point, $previousValue);
            $points->push($point);
            $previousValue = $point->current_value;
        }

        return $points;
    }

    private function fetchBucketedPoints(Carbon $windowStart, int $rangeMinutes, int $maxPoints): Collection
    {
        $goldKey = $this->calculator->gold18Key();
        $ounceKey = $this->calculator->ounceKey();
        $bucketSeconds = max(60, (int)ceil(($rangeMinutes * 60) / $maxPoints));

        $bucketQuery = DB::table('price_points as g')
            ->join('price_points as o', function ($join) {
                $join->on('g.fetched_at', '=', 'o.fetched_at');
            })
            ->selectRaw('MAX(g.fetched_at) as bucket_fetched_at')
            ->where('g.item_key', $goldKey)
            ->where('o.item_key', $ounceKey)
            ->where('g.fetched_at', '>=', $windowStart)
            ->where('g.current_value', '>', 0)
            ->where('o.current_value', '>', 0)
            ->groupByRaw('FLOOR(UNIX_TIMESTAMP(g.fetched_at) / ' . $bucketSeconds . ')');

        $rows = DB::table('price_points as g')
            ->join('price_points as o', function ($join) {
                $join->on('g.fetched_at', '=', 'o.fetched_at');
            })
            ->joinSub($bucketQuery, 'buckets', function ($join) {
                $join->on('g.fetched_at', '=', 'buckets.bucket_fetched_at');
            })
            ->where('g.item_key', $goldKey)
            ->where('o.item_key', $ounceKey)
            ->orderBy('g.fetched_at')
            ->select([
                'g.fetched_at',
                'g.current_value as gold18_current',
                'g.high_value as gold18_high',
                'g.low_value as gold18_low',
                'o.current_value as ounce_current',
                'o.high_value as ounce_high',
                'o.low_value as ounce_low',
            ])
            ->get();

        return $this->mapRowsToPoints($rows);
    }

    public function fetchAnalytics(Carbon $windowStart, Collection $points, bool $fromFullRange): array
    {
        if ($fromFullRange) {
            return $this->analyticsFromRows($this->fetchJoinedRows($windowStart));
        }

        return $this->analyticsFromPoints($points);
    }

    private function analyticsFromRows(Collection $rows): array
    {
        $points = $this->mapRowsToPoints($rows);

        return $this->analyticsFromPoints($points);
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
