<?php

namespace App\Services;

use App\Support\MarketItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CoinBubbleCalculator
{
    /**
     * @return array<string, array{
     *   market: float|null,
     *   intrinsic: float|null,
     *   bubble: float|null,
     *   bubble_percent: float|null,
     *   unavailable_reason?: string,
     *   coin_fetched_at?: string|null,
     *   reference_fetched_at?: string|null,
     *   coin_direction?: string,
     *   reference_direction?: string
     * }>
     */
    public function forItems(Collection $items): array
    {
        $config = config('gold.coin_bubble', []);
        $referenceName = (string)($config['reference_item'] ?? 'طلای ۱۸ عیار');
        $referenceKey = PersianNumber::label($referenceName);
        $referencePurity = (float)($config['reference_purity'] ?? 0.750);
        $syncThreshold = max(60, (int)($config['sync_threshold_seconds'] ?? 300));
        $coinSpecs = $config['coins'] ?? [];

        if ($referencePurity <= 0 || $coinSpecs === []) {
            return [];
        }

        $specsByKey = [];
        foreach ($coinSpecs as $name => $spec) {
            $specsByKey[PersianNumber::label((string)$name)] = $spec;
        }

        $referenceItem = $items->first(
            fn(MarketItem $item) => $item->key === $referenceKey
                || PersianNumber::label($item->name) === $referenceKey
        );
        $referencePrice = $referenceItem?->latestPrice?->current_value;
        $referenceFetchedAt = $referenceItem?->latestPrice?->fetched_at;
        $referenceDirection = $referenceItem?->latestPrice?->direction ?? 'none';

        if ($referencePrice === null || (float)$referencePrice <= 0) {
            return [];
        }

        $referencePrice = (float)$referencePrice;
        $result = [];

        foreach ($items as $item) {
            if ($item->category !== 'coin') {
                continue;
            }

            $spec = $specsByKey[PersianNumber::label($item->name)]
                ?? $specsByKey[$item->key]
                ?? null;
            if ($spec === null) {
                continue;
            }

            $weight = (float)($spec['weight_g'] ?? 0);
            $purity = (float)($spec['purity'] ?? 0);
            $market = (float)($item->latestPrice?->current_value ?? 0);
            $coinFetchedAt = $item->latestPrice?->fetched_at;
            $coinDirection = $item->latestPrice?->direction ?? 'none';
            if ($weight <= 0 || $purity <= 0 || $market <= 0) {
                continue;
            }

            $meta = [
                'coin_fetched_at' => $coinFetchedAt?->toIso8601String(),
                'reference_fetched_at' => $referenceFetchedAt?->toIso8601String(),
                'coin_direction' => $coinDirection,
                'reference_direction' => $referenceDirection,
            ];

            if (!$this->isSynchronized($coinFetchedAt, $referenceFetchedAt, $syncThreshold)) {
                $result[$item->key] = [
                    'market' => round($market, 0),
                    'intrinsic' => null,
                    'bubble' => null,
                    'bubble_percent' => null,
                    'unavailable_reason' => 'داده هم‌زمان نیست',
                    ...$meta,
                ];
                continue;
            }

            $intrinsic = $weight * ($purity / $referencePurity) * $referencePrice;
            if ($intrinsic <= 0) {
                continue;
            }

            $bubble = $market - $intrinsic;
            $result[$item->key] = [
                'market' => round($market, 0),
                'intrinsic' => round($intrinsic, 0),
                'bubble' => round($bubble, 0),
                'bubble_percent' => round(($bubble / $intrinsic) * 100, 2),
                ...$meta,
            ];
        }

        return $result;
    }

    private function isSynchronized(?Carbon $coinAt, ?Carbon $referenceAt, int $thresholdSeconds): bool
    {
        if (!$coinAt || !$referenceAt) {
            return false;
        }

        return abs($coinAt->diffInSeconds($referenceAt)) <= $thresholdSeconds;
    }
}
