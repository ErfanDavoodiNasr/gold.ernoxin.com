<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Chart-history spike filter. Uses local neighbor median — not the rial/toman
 * 8–12× band (that belongs only in PriceNormalizer at ingest).
 */
final class OutlierFilter
{
    private int $neighborRadius;
    private float $maxRelativeDeviation;

    public function __construct()
    {
        $this->neighborRadius = max(1, (int)config('gold.outlier.chart_neighbor_radius', 2));
        $this->maxRelativeDeviation = max(0.01, (float)config('gold.outlier.chart_max_relative_deviation', 0.15));
    }

    public function filter(Collection $points, callable $accessor): Collection
    {
        $values = $points->values();
        $count = $values->count();
        if ($count === 0) {
            return $values;
        }

        $nums = [];
        foreach ($values as $index => $point) {
            $value = $accessor($point);
            $nums[$index] = $this->isUsable($value) ? (float)$value : null;
        }

        return $values->filter(function ($point, $index) use ($nums, $count) {
            $value = $nums[$index];
            if ($value === null) {
                return false;
            }

            $neighbors = [];
            $from = max(0, $index - $this->neighborRadius);
            $to = min($count - 1, $index + $this->neighborRadius);
            for ($j = $from; $j <= $to; $j++) {
                if ($j === $index || $nums[$j] === null) {
                    continue;
                }
                $neighbors[] = $nums[$j];
            }

            if ($neighbors === []) {
                return true;
            }

            sort($neighbors);
            $median = $neighbors[(int)floor((count($neighbors) - 1) / 2)];
            if ($median <= 0) {
                return true;
            }

            return (abs($value - $median) / $median) <= $this->maxRelativeDeviation;
        })->values();
    }

    public function isUsable(mixed $value): bool
    {
        return $value !== null
            && is_numeric($value)
            && (float)$value > 0;
    }
}
