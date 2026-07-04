<?php

namespace App\Services;

use Illuminate\Support\Collection;

final class OutlierFilter
{
    private float $spikeMin;
    private float $spikeMax;

    public function __construct()
    {
        $this->spikeMin = (float)config('gold.outlier.spike_min', 8.0);
        $this->spikeMax = (float)config('gold.outlier.spike_max', 12.0);
    }

    public function filter(Collection $points, callable $accessor): Collection
    {
        $reference = null;

        return $points->values()->filter(function ($point) use ($accessor, &$reference) {
            $value = $accessor($point);
            if (!$this->isUsable($value)) {
                return false;
            }

            $value = (float)$value;
            if ($reference !== null) {
                $ratio = $value / $reference;
                if ($ratio >= $this->spikeMin && $ratio <= $this->spikeMax) {
                    return false;
                }
                if ($ratio >= 1 / $this->spikeMax && $ratio <= 1 / $this->spikeMin) {
                    return false;
                }
            }

            $reference = $value;

            return true;
        })->values();
    }

    public function isUsable(mixed $value): bool
    {
        return $value !== null
            && is_numeric($value)
            && (float)$value > 0;
    }
}
