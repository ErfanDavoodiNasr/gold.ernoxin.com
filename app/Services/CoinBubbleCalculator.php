<?php

namespace App\Services;

use App\Support\MarketItem;
use Illuminate\Support\Collection;

class CoinBubbleCalculator
{
    /**
     * @return array<string, array{market:float,intrinsic:float,bubble:float,bubble_percent:float}>
     */
    public function forItems(Collection $items): array
    {
        $config = config('gold.coin_bubble', []);
        $referenceName = (string)($config['reference_item'] ?? 'طلای ۱۸ عیار');
        $referencePurity = (float)($config['reference_purity'] ?? 0.750);
        $coinSpecs = $config['coins'] ?? [];

        if ($referencePurity <= 0 || $coinSpecs === []) {
            return [];
        }

        $referencePrice = $items
            ->first(fn(MarketItem $item) => $item->name === $referenceName || $item->key === PersianNumber::label($referenceName))
            ?->latestPrice
            ?->current_value;

        if ($referencePrice === null || (float)$referencePrice <= 0) {
            return [];
        }

        $referencePrice = (float)$referencePrice;
        $result = [];

        foreach ($items as $item) {
            if ($item->category !== 'coin') {
                continue;
            }

            $spec = $coinSpecs[$item->name] ?? null;
            if ($spec === null) {
                continue;
            }

            $weight = (float)($spec['weight_g'] ?? 0);
            $purity = (float)($spec['purity'] ?? 0);
            $market = (float)($item->latestPrice?->current_value ?? 0);
            if ($weight <= 0 || $purity <= 0 || $market <= 0) {
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
            ];
        }

        return $result;
    }
}
