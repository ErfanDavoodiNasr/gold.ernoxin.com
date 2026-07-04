<?php

namespace App\Services;

class PriceNormalizer
{
    private float $rialRatioMin;
    private float $rialRatioMax;

    public function __construct()
    {
        $this->rialRatioMin = (float)config('gold.outlier.spike_min', 8.0);
        $this->rialRatioMax = (float)config('gold.outlier.spike_max', 12.0);
    }

    public function isUsdItem(?string $currency, ?string $category = null): bool
    {
        $normalized = PersianNumber::label($currency ?? '');

        return $normalized === '$'
            || str_contains($normalized, '$')
            || str_contains($normalized, 'دلار');
    }

    public function looksLikeRialSpike(float $value, float $referenceToman): bool
    {
        if ($referenceToman <= 0) {
            return false;
        }

        $ratio = $value / $referenceToman;

        return $ratio >= $this->rialRatioMin && $ratio <= $this->rialRatioMax;
    }

    public function looksLikeTomanDip(float $value, float $referenceToman): bool
    {
        if ($referenceToman <= 0) {
            return false;
        }

        $ratio = $value / $referenceToman;

        return $ratio >= (1 / $this->rialRatioMax) && $ratio <= (1 / $this->rialRatioMin);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    public function normalizeRow(array $row, ?float $referenceToman, bool $isUsd = false): ?array
    {
        $currentCurrency = $row['current']['currency'] ?? null;
        $current = $this->normalizeValue($row['current']['value'] ?? null, $currentCurrency, $referenceToman, $isUsd);
        if ($current === null) {
            return null;
        }

        $nextReference = $current;
        $yesterdayCurrency = $row['yesterdayAvg']['currency'] ?? $currentCurrency;

        $row['current']['value'] = $current;
        $row['high']['value'] = $this->normalizeValue($row['high']['value'] ?? null, $currentCurrency, $nextReference, $isUsd);
        $row['low']['value'] = $this->normalizeValue($row['low']['value'] ?? null, $currentCurrency, $nextReference, $isUsd);
        $row['yesterdayAvg']['value'] = $this->normalizeValue($row['yesterdayAvg']['value'] ?? null, $yesterdayCurrency, $nextReference, $isUsd);

        if (isset($row['change']['value']) && $row['change']['value'] !== null) {
            $changeValue = abs((float)$row['change']['value']);
            $normalizedChange = $this->normalizeValue($changeValue, $currentCurrency, $current, $isUsd);
            if ($normalizedChange !== null) {
                $row['change']['value'] = $normalizedChange * ($row['change']['direction'] === 'desc' ? -1 : 1);
            }
        }

        return $row;
    }

    public function normalizeValue(?float $value, ?string $currency, ?float $referenceToman, bool $isUsd = false): ?float
    {
        if ($value === null || !is_numeric($value)) {
            return null;
        }

        $value = (float)$value;
        if ($value <= 0) {
            return null;
        }

        if ($isUsd) {
            return round($value, 4);
        }

        if ($this->isRialCurrency($currency)) {
            return round($value / 10, 4);
        }

        if ($this->isTomanCurrency($currency)) {
            return round($value, 4);
        }

        if ($referenceToman !== null && $referenceToman > 0) {
            $ratio = $value / $referenceToman;
            if ($ratio >= $this->rialRatioMin && $ratio <= $this->rialRatioMax) {
                return round($value / 10, 4);
            }
            if ($ratio >= (1 / $this->rialRatioMax) && $ratio <= (1 / $this->rialRatioMin)) {
                return round($value * 10, 4);
            }
        }

        return round($value, 4);
    }

    public function isRialCurrency(?string $currency): bool
    {
        if ($currency === null || trim($currency) === '') {
            return false;
        }

        $normalized = PersianNumber::label($currency);

        return str_contains($normalized, 'ریال') && !str_contains($normalized, 'تومان');
    }

    public function isTomanCurrency(?string $currency): bool
    {
        if ($currency === null || trim($currency) === '') {
            return false;
        }

        return str_contains(PersianNumber::label($currency), 'تومان');
    }
}
