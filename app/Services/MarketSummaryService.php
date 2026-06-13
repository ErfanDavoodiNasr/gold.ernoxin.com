<?php

namespace App\Services;

use App\Models\FetchLog;
use App\Models\MarketItem;
use App\Models\PricePoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class MarketSummaryService
{
    public function items(): Collection
    {
        return $this->cached()['items'];
    }

    public function cached(): array
    {
        $ttl = max(5, (int)config('gold.summary_cache_seconds', 20));

        return Cache::remember('gold:market-summary:data', $ttl, function () {
            return [
                'items' => MarketItem::with('latestPrice')
                    ->where('is_active', true)
                    ->orderBy('category')
                    ->orderBy('name')
                    ->get(),
                'lastFetch' => FetchLog::latest('finished_at')->first(),
            ];
        });
    }

    public function lastFetch(): ?FetchLog
    {
        return $this->cached()['lastFetch'];
    }

    public function apiPayload(): array
    {
        $data = $this->cached();

        return [
            'items' => $data['items']->map(fn($item) => $this->itemResource($item)),
            'lastFetch' => $this->fetchLogResource($data['lastFetch']),
            'config' => [
                'sourceName' => config('gold.source_name'),
                'sourceUrl' => config('gold.source_url'),
                'chartDefaultRange' => $this->normalizeRangeKey(config('gold.chart_default_range', '1d')),
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

    public function itemResource(MarketItem $item): array
    {
        $price = $this->displayPrice($item);

        return [
            'id' => $item->id,
            'name' => $item->name,
            'category' => $item->category,
            'currency' => $item->currency,
            'current' => $price?->current_value,
            'high' => $price?->high_value,
            'low' => $price?->low_value,
            'change' => $price?->change_value,
            'percent' => $price?->change_percent,
            'direction' => $price?->direction ?? 'none',
            'fetchedAt' => $price?->fetched_at?->toIso8601String(),
        ];
    }

    private function displayPrice(MarketItem $item): ?PricePoint
    {
        $latest = $item->latestPrice;
        if ($this->isUsablePrice($latest?->current_value)) {
            return $latest;
        }

        return $item->prices()
            ->whereNotNull('current_value')
            ->where('current_value', '>', 0)
            ->latest('fetched_at')
            ->first();
    }

    private function isUsablePrice($value): bool
    {
        return $value !== null && is_numeric($value) && (float)$value > 0;
    }

    private function fetchLogResource(?FetchLog $log): ?array
    {
        if (!$log) {
            return null;
        }

        return [
            'status' => $log->status,
            'items_count' => $log->items_count,
            'started_at' => $log->started_at?->toIso8601String(),
            'finished_at' => $log->finished_at?->toIso8601String(),
        ];
    }

    private function normalizeRangeKey($value): string
    {
        $raw = strtolower(trim((string)$value));

        if (preg_match('/^(\d+)\s*([hd])$/', $raw, $matches)) {
            $amount = max(1, (int)$matches[1]);
            return $matches[2] === 'h' ? "{$amount}h" : "{$amount}d";
        }

        return max(1, (int)$raw) . 'd';
    }
}
