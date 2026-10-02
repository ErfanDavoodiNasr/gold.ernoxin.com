<?php

namespace App\Services;

use App\Models\PricePoint;
use App\Support\LastFetch;
use App\Support\MarketItem;
use App\Support\StampedeCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
        return collect($this->apiPayload()['items'] ?? [])
            ->map(fn(array $row) => $this->hydrateItem($row))
            ->values();
    }

    /** Final API array — never cache Eloquent models. */
    public function apiPayload(): array
    {
        $ttl = $this->summaryCacheTtl();

        // v2: plain API arrays (not Eloquent) — old key may still hold models until TTL.
        return StampedeCache::remember('gold:market-summary:data:v2', $ttl, function () {
            $items = $this->catalog->allWithLatestPrices();
            $dailyRanges = $this->todayRangesForMissing($items);
            $lastFetch = $this->fetchStatus->last();

            return [
                'items' => $items
                    ->map(fn(MarketItem $item) => $this->itemResource($item, $dailyRanges[$item->key] ?? null))
                    ->values()
                    ->all(),
                'lastFetch' => $lastFetch?->toArray(),
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
        });
    }

    /** Keep summary TTL within fetch interval so stale windows stay short if forget fails. */
    private function summaryCacheTtl(): int
    {
        $configured = max(5, (int)config('gold.summary_cache_seconds', 10));
        $fetchCap = max(5, (int)config('gold.fetch_interval_minutes', 5) * 60);

        return min($configured, $fetchCap);
    }

    /**
     * Only aggregate today for keys whose latest row lacks usable high/low.
     *
     * @param Collection<int, MarketItem> $items
     * @return array<string, array{high: float, low: float}>
     */
    private function todayRangesForMissing(Collection $items): array
    {
        $keys = $items
            ->filter(function (MarketItem $item) {
                $price = $item->latestPrice;

                return !$this->isUsablePrice($price?->high_value)
                    || !$this->isUsablePrice($price?->low_value);
            })
            ->pluck('key')
            ->filter()
            ->unique()
            ->values()
            ->all();

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
        $fetchedAt = $price?->fetched_at;
        $ageSeconds = $fetchedAt ? (int)$fetchedAt->diffInSeconds(now()) : null;
        $staleAfter = max(120, (int)config('gold.fetch_interval_minutes', 1) * 60 * 2);

        return [
            'id' => $item->id,
            'key' => $item->key,
            'slug' => $item->slug,
            'name' => $item->name,
            'category' => $item->category,
            'currency' => $item->currency,
            'unitLabel' => $this->unitLabel($item),
            'current' => $price?->current_value,
            'high' => $this->isUsablePrice($high) ? $high : null,
            'low' => $this->isUsablePrice($low) ? $low : null,
            'change' => PersianNumber::signedByDirection($price?->change_value, $direction),
            'percent' => PersianNumber::signedByDirection($price?->change_percent, $direction),
            'direction' => $direction,
            'fetchedAt' => $fetchedAt?->toIso8601String(),
            'ageSeconds' => $ageSeconds,
            'stale' => $ageSeconds !== null && $ageSeconds > $staleAfter,
        ];
    }

    private function unitLabel(MarketItem $item): string
    {
        if ($item->slug === 'mozaneh') {
            return (string)config('gold.mozaneh_unit_label', 'مظنه / مثقال');
        }

        return $item->isUsd() ? 'دلار' : 'تومان';
    }

    /** Rebuild MarketItem for Blade/SEO from cached API row. */
    private function hydrateItem(array $row): MarketItem
    {
        $price = null;
        if ($this->isUsablePrice($row['current'] ?? null)) {
            $price = new PricePoint([
                'current_value' => $row['current'],
                'high_value' => $row['high'] ?? null,
                'low_value' => $row['low'] ?? null,
                'change_value' => isset($row['change']) ? (float)$row['change'] : null,
                'change_percent' => isset($row['percent']) ? (float)$row['percent'] : null,
                'direction' => $row['direction'] ?? 'none',
                'fetched_at' => $row['fetchedAt'] ?? null,
            ]);
        }

        $name = (string)($row['name'] ?? '');

        return new MarketItem(
            id: (int)($row['id'] ?? 0),
            key: (string)($row['key'] ?? PersianNumber::label($name)),
            name: $name,
            category: (string)($row['category'] ?? 'gold'),
            currency: $row['currency'] ?? null,
            latestPrice: $price,
            slug: (string)($row['slug'] ?? ''),
        );
    }

    public function lastFetch(): ?LastFetch
    {
        $value = $this->apiPayload()['lastFetch'] ?? null;
        if (!is_array($value) || empty($value['status'])) {
            return null;
        }

        return new LastFetch(
            status: (string)$value['status'],
            itemsCount: (int)($value['items_count'] ?? 0),
            startedAt: !empty($value['started_at']) ? Carbon::parse($value['started_at']) : null,
            finishedAt: !empty($value['finished_at']) ? Carbon::parse($value['finished_at']) : null,
            message: isset($value['message']) ? (string)$value['message'] : null,
        );
    }
}
