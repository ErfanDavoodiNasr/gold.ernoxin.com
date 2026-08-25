<?php

namespace App\Services;

use App\Support\LastFetch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class FetchStatusStore
{
    private const CACHE_KEY = 'gold:last-fetch';

    public function start(string $reference): void
    {
        Cache::put(self::CACHE_KEY, [
            'status' => 'running',
            'items_count' => 0,
            'reference_id' => $reference,
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'message' => null,
        ], now()->addDays(7));
    }

    public function succeed(int $itemsCount): void
    {
        $this->finish('success', $itemsCount);
    }

    private function finish(string $status, int $itemsCount, ?string $message = null): void
    {
        $current = Cache::get(self::CACHE_KEY, []);
        $startedAt = $current['started_at'] ?? now()->toIso8601String();

        Cache::put(self::CACHE_KEY, [
            'status' => $status,
            'items_count' => $itemsCount,
            'reference_id' => $current['reference_id'] ?? null,
            'started_at' => $startedAt,
            'finished_at' => now()->toIso8601String(),
            'message' => $message,
        ], now()->addDays(7));
    }

    public function partial(int $itemsCount, string $message): void
    {
        $this->finish('partial', $itemsCount, $message);
    }

    public function fail(string $message): void
    {
        $this->finish('failed', 0, $message);
    }

    public function last(): ?LastFetch
    {
        $value = Cache::get(self::CACHE_KEY);
        if (!is_array($value) || empty($value['status'])) {
            return $this->fallbackFromPricePoints();
        }

        return new LastFetch(
            status: (string)$value['status'],
            itemsCount: (int)($value['items_count'] ?? 0),
            startedAt: !empty($value['started_at']) ? Carbon::parse($value['started_at']) : null,
            finishedAt: !empty($value['finished_at']) ? Carbon::parse($value['finished_at']) : null,
            message: isset($value['message']) ? (string)$value['message'] : null,
        );
    }

    private function fallbackFromPricePoints(): ?LastFetch
    {
        $finishedAt = \App\Models\PricePoint::max('fetched_at');
        if (!$finishedAt) {
            return null;
        }

        return new LastFetch(
            status: 'success',
            itemsCount: count(app(MarketCatalog::class)->keys()),
            finishedAt: Carbon::parse($finishedAt),
        );
    }

    public function lastSuccessStartedAt(): ?Carbon
    {
        $last = Cache::get(self::CACHE_KEY);
        if (is_array($last) && in_array($last['status'] ?? null, ['success', 'partial'], true) && !empty($last['started_at'])) {
            return Carbon::parse($last['started_at']);
        }

        $fallback = $this->fallbackFromPricePoints();

        return $fallback?->finishedAt;
    }

    public function isActivelyRunning(int $staleAfterSeconds = 90): bool
    {
        $last = Cache::get(self::CACHE_KEY);
        if (!is_array($last) || ($last['status'] ?? null) !== 'running' || empty($last['started_at'])) {
            return false;
        }

        return Carbon::parse($last['started_at'])->gt(now()->subSeconds(max(1, $staleAfterSeconds)));
    }
}
