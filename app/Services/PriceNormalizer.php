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
        if ($normalized === '') {
            return false;
        }

        // Intentional USD markers only — do not treat bare "USD" inside unrelated Persian text.
        return $normalized === '$'
            || $normalized === 'usd'
            || str_contains($normalized, '$')
            || str_contains($normalized, 'دلار')
            || preg_match('/\busd\b/i', $normalized) === 1;
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

        $direction = $row['change']['direction'] ?? 'none';
        if (isset($row['change']['value']) && $row['change']['value'] !== null) {
            $changeValue = abs((float)$row['change']['value']);
            $normalizedChange = $this->normalizeValue($changeValue, $currentCurrency, $current, $isUsd);
            if ($normalizedChange !== null) {
                $row['change']['value'] = $normalizedChange * ($direction === 'desc' ? -1 : 1);
            }
        }
        if (isset($row['change']['percent']) && $row['change']['percent'] !== null) {
            $percent = abs((float)$row['change']['percent']);
            $row['change']['percent'] = $direction === 'desc' ? -$percent : $percent;
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
            if ($this->looksLikeRialSpike($value, $referenceToman)) {
                return null;
            }
            if ($this->looksLikeTomanDip($value, $referenceToman)) {
                return null;
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
        if (str_contains($normalized, 'تومان') || str_contains($normalized, 'تومن')) {
            return false;
        }

        return str_contains($normalized, 'ریال')
            || $normalized === 'irr'
            || preg_match('/\birr\b/i', $normalized) === 1
            || preg_match('/\brial\b/i', $normalized) === 1;
    }

    public function isTomanCurrency(?string $currency): bool
    {
        if ($currency === null || trim($currency) === '') {
            return false;
        }

        $normalized = PersianNumber::label($currency);

        return str_contains($normalized, 'تومان') || str_contains($normalized, 'تومن');
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

    /** Repair stored rows whose current_value still sits in the rial/toman spike band. */
    public function repairAgainstReference(float $value, float $referenceToman): float
    {
        if ($referenceToman <= 0 || $value <= 0) {
            return $value;
        }

        if ($this->looksLikeRialSpike($value, $referenceToman)) {
            return round($value / 10, 4);
        }

        if ($this->looksLikeTomanDip($value, $referenceToman)) {
            return round($value * 10, 4);
        }

        return $value;
    }
}
