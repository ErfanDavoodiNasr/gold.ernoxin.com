<?php

namespace App\Services;

use App\Models\PricePoint;
use App\Support\LastFetch;
use App\Support\MarketItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class MarketSummaryService
{
    public function __construct(
        private MarketCatalog    $catalog,
        private FetchStatusStore $fetchStatus,
        private RangeParser      $rangeParser,
    )
    {
    }

    public function items(): Collection
    {
        return $this->cached()['items'];
    }

    public function cached(): array
    {
        $ttl = max(5, (int)config('gold.summary_cache_seconds', 20));

        return Cache::remember('gold:market-summary:data', $ttl, function () {
            return [
                'items' => $this->catalog->allWithLatestPrices(),
                'lastFetch' => $this->fetchStatus->last(),
            ];
        });
    }

    public function lastFetch(): ?LastFetch
    {
        return $this->cached()['lastFetch'];
    }

    public function apiPayload(): array
    {
        $data = $this->cached();
        $dailyRanges = $this->todayRanges($data['items']);

        return [
            'items' => $data['items']->map(fn(MarketItem $item) => $this->itemResource($item, $dailyRanges[$item->key] ?? null))->values(),
            'lastFetch' => $data['lastFetch']?->toArray(),
            'config' => [
                'sourceName' => config('gold.source_name'),
                'sourceUrl' => config('gold.source_url'),
                'chartDefaultRange' => $this->rangeParser->canonicalKey(config('gold.chart_default_range', '1d')),
                'chartAvailableRanges' => config('gold.chart_available_ranges'),
                'historyMaxDays' => config('gold.history_max_days'),
                'chartMaxPoints' => config('gold.chart_max_points'),
                'autoRefreshSeconds' => config('gold.frontend_refresh_seconds'),
                'themeDefault' => config('gold.theme_default'),
                'themeAccent' => config('gold.theme_accent'),
                'features' => config('gold.features'),
            ],
        ];
    }

    /**
     * @param Collection<int, MarketItem> $items
     * @return array<string, array{high: float, low: float}>
     */
    private function todayRanges(Collection $items): array
    {
        $keys = $items->pluck('key')->filter()->unique()->values()->all();
        if ($keys === []) {
            return [];
        }

        $rows = PricePoint::query()
            ->whereIn('item_key', $keys)
            ->whereBetween('fetched_at', [now()->startOfDay(), now()->endOfDay()])
            ->where('current_value', '>', 0)
            ->groupBy('item_key')
            ->selectRaw('item_key, MAX(current_value) as high_value, MIN(current_value) as low_value')
            ->get();

        $ranges = [];
        foreach ($rows as $row) {
            if (!$this->isUsablePrice($row->high_value) || !$this->isUsablePrice($row->low_value)) {
                continue;
            }
            $ranges[$row->item_key] = [
                'high' => (float)$row->high_value,
                'low' => (float)$row->low_value,
            ];
        }

        return $ranges;
    }

    private function isUsablePrice($value): bool
    {
        return $value !== null && is_numeric($value) && (float)$value > 0;
    }

    public function itemResource(MarketItem $item, ?array $dailyRange = null): array
    {
        // MarketCatalog::allWithLatestPrices() already resolves the latest row
        // with a usable current_value (filtered via current_value > 0) in a
        // single batched query, so latestPrice is either the latest usable
        // PricePoint or null. The previous per-item fallback query here was
        // redundant and triggered an N+1 on every uncached summary build.
        $price = $this->isUsablePrice($item->latestPrice?->current_value)
            ? $item->latestPrice
            : null;

        $high = $price?->high_value;
        $low = $price?->low_value;
        if ((!$this->isUsablePrice($high) || !$this->isUsablePrice($low)) && $dailyRange) {
            $high = $dailyRange['high'];
            $low = $dailyRange['low'];
        }

        $direction = $price?->direction ?? 'none';

        return [
            'id' => $item->id,
            'slug' => $item->slug,
            'name' => $item->name,
            'category' => $item->category,
            'currency' => $item->currency,
            'current' => $price?->current_value,
            'high' => $this->isUsablePrice($high) ? $high : null,
            'low' => $this->isUsablePrice($low) ? $low : null,
            'change' => PersianNumber::signedByDirection($price?->change_value, $direction),
            'percent' => PersianNumber::signedByDirection($price?->change_percent, $direction),
            'direction' => $direction,
            'fetchedAt' => $price?->fetched_at?->toIso8601String(),
        ];
    }
}
