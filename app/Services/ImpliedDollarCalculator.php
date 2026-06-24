<?php

namespace App\Services;

class ImpliedDollarCalculator
{
    public function gold18Key(): string
    {
        return PersianNumber::label(config('gold.implied_dollar.sources.gold_18', 'طلای ۱۸ عیار'));
    }

    public function ounceKey(): string
    {
        return PersianNumber::label(config('gold.implied_dollar.sources.ounce', 'انس طلا'));
    }

    public function computeRange(float $gold18High, float $gold18Low, float $ounceHigh, float $ounceLow): array
    {
        $high = $this->compute($gold18High, $ounceLow);
        $low = $this->compute($gold18Low, $ounceHigh);

        if ($high === null || $low === null) {
            return ['high' => null, 'low' => null];
        }

        return [
            'high' => max($high, $low),
            'low' => min($high, $low),
        ];
    }

    public function compute(float $gold18PerGramToman, float $ounceUsd): ?float
    {
        if ($gold18PerGramToman <= 0 || $ounceUsd <= 0) {
            return null;
        }

        $karat18 = (int)config('gold.implied_dollar.karat_numerator', 18);
        $karat24 = (int)config('gold.implied_dollar.karat_denominator', 24);

        return round(
            ($gold18PerGramToman * $this->troyOunceGrams() * $karat24) / ($ounceUsd * $karat18),
            (int)config('gold.implied_dollar.decimals', 0)
        );
    }

    public function troyOunceGrams(): float
    {
        return (float)config('gold.implied_dollar.troy_ounce_grams', 31.1034768);
    }
}
