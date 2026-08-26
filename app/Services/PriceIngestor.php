<?php

namespace App\Services;

use App\Models\PricePoint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PriceIngestor
{
    public function __construct(
        private EstjtScraper     $scraper,
        private PriceNormalizer  $normalizer,
        private MarketCatalog    $catalog,
        private FetchStatusStore $fetchStatus,
    )
    {
    }

    public function fetchAndStore(): array
    {
        $reference = (string)Str::uuid();
        $source = config('gold.source_key', 'estjt');

        try {
            $this->fetchStatus->start($reference);

            $payload = $this->scraper->fetch();
            $count = DB::transaction(fn() => $this->store($payload));
            $expected = count($this->catalog->keys());
            if ($count === 0) {
                throw new \RuntimeException('هیچ قیمت معتبری ذخیره نشد.');
            }
            if ($expected > 0 && $count < $expected) {
                $this->fetchStatus->partial($count, "فقط {$count} از {$expected} نماد به‌روزرسانی شد.");
            } else {
                $this->fetchStatus->succeed($count);
            }
            $this->clearCaches();
            Cache::increment('gold:price-data-version');
            $this->pruneOldPoints();

            return ['referenceId' => $reference, 'items' => $count, 'payload' => $payload];
        } catch (\Throwable $e) {
            $this->markFetchFailed($reference, $source, $e);
            throw $e;
        }
    }

    public function store(array $payload): int
    {
        $fetchedAt = isset($payload['source']['fetchedAt']) ? Carbon::parse($payload['source']['fetchedAt']) : now();
        $referencePrices = $this->latestReferencePrices();
        $pending = [];

        foreach (['gold', 'coin'] as $group) {
            foreach (($payload[$group] ?? []) as $row) {
                $normalized = PersianNumber::label($row['type']);
                $definition = $this->catalog->findByKey($normalized);
                if (!$definition) {
                    report(new \RuntimeException("Unknown market item skipped: {$normalized}"));
                    continue;
                }

                $referenceToman = $referencePrices[$normalized] ?? null;
                $isUsd = $this->normalizer->isUsdItem($row['current']['currency'] ?? $definition->currency, $group);

                $rawRow = $row;
                $row = $this->normalizer->normalizeRow($row, $referenceToman, $isUsd);
                if ($row === null || !$this->validPrice($row['current']['value'] ?? null)) {
                    report(new \RuntimeException("Invalid zero or empty price skipped for {$normalized}"));
                    continue;
                }

                $current = (float)$row['current']['value'];
                $pending[] = [
                    'item_key' => $normalized,
                    'current' => $current,
                    'source_high' => $row['high']['value'] ?? null,
                    'source_low' => $row['low']['value'] ?? null,
                    'yesterday_avg_value' => $row['yesterdayAvg']['value'],
                    'change_value' => $row['change']['value'],
                    'change_percent' => $row['change']['percent'],
                    'direction' => $row['change']['direction'],
                    'raw_payload' => $rawRow,
                ];
                $referencePrices[$normalized] = $current;
            }
        }

        if ($pending === []) {
            return 0;
        }

        $dayStats = $this->dailyRangesFor(
            array_column($pending, 'item_key'),
            $fetchedAt,
        );

        $now = now();
        $rows = [];
        foreach ($pending as $item) {
            [$high, $low] = $this->resolveDailyRange(
                $item['current'],
                $item['source_high'],
                $item['source_low'],
                $dayStats[$item['item_key']] ?? null,
            );

            $rows[] = [
                'item_key' => $item['item_key'],
                'fetched_at' => $fetchedAt,
                'current_value' => $item['current'],
                'high_value' => $high,
                'low_value' => $low,
                'yesterday_avg_value' => $item['yesterday_avg_value'],
                'change_value' => $item['change_value'],
                'change_percent' => $item['change_percent'],
                'direction' => $item['direction'],
                'raw_payload' => json_encode($item['raw_payload'], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        PricePoint::upsert(
            $rows,
            ['item_key', 'fetched_at'],
            [
                'current_value',
                'high_value',
                'low_value',
                'yesterday_avg_value',
                'change_value',
                'change_percent',
                'direction',
                'raw_payload',
                'updated_at',
            ],
        );

        return count($rows);
    }

    /** @return array<string, float> */
    private function latestReferencePrices(): array
    {
        $keys = $this->catalog->keys();
        if ($keys === []) {
            return [];
        }

        $latest = PricePoint::query()
            ->selectRaw('item_key, MAX(fetched_at) as max_fetched_at')
            ->whereIn('item_key', $keys)
            ->whereNotNull('current_value')
            ->where('current_value', '>', 0)
            ->groupBy('item_key');

        return PricePoint::query()
            ->joinSub($latest, 'latest', function ($join) {
                $join->on('price_points.item_key', '=', 'latest.item_key')
                    ->on('price_points.fetched_at', '=', 'latest.max_fetched_at');
            })
            ->whereIn('price_points.item_key', $keys)
            ->pluck('price_points.current_value', 'price_points.item_key')
            ->map(fn($value) => (float)$value)
            ->all();
    }

    private function validPrice($value): bool
    {
        return $value !== null && is_numeric($value) && (float)$value > 0;
    }

    /**
     * One batched day-range query for all keys in this ingest.
     *
     * @param list<string> $keys
     * @return array<string, array{high: float, low: float}>
     */
    private function dailyRangesFor(array $keys, Carbon $fetchedAt): array
    {
        $keys = array_values(array_unique($keys));
        if ($keys === []) {
            return [];
        }

        $rows = PricePoint::query()
            ->whereIn('item_key', $keys)
            ->whereBetween('fetched_at', [$fetchedAt->copy()->startOfDay(), $fetchedAt->copy()->endOfDay()])
            ->where('current_value', '>', 0)
            ->groupBy('item_key')
            ->selectRaw('item_key, MAX(current_value) as high_value, MIN(current_value) as low_value')
            ->get();

        $ranges = [];
        foreach ($rows as $row) {
            if (!$this->validPrice($row->high_value) || !$this->validPrice($row->low_value)) {
                continue;
            }
            $ranges[$row->item_key] = [
                'high' => (float)$row->high_value,
                'low' => (float)$row->low_value,
            ];
        }

        return $ranges;
    }

    /**
     * Prefer source high/low; otherwise fall back to today's stored min/max.
     *
     * @param array{high: float, low: float}|null $dayStats
     * @return array{0: float, 1: float}
     */
    private function resolveDailyRange(float $current, $high, $low, ?array $dayStats): array
    {
        if ($this->validPrice($high) && $this->validPrice($low)) {
            return [(float)$high, (float)$low];
        }

        $dayHigh = $dayStats['high'] ?? $current;
        $dayLow = $dayStats['low'] ?? $current;

        return [
            max($dayHigh, $current),
            min($dayLow, $current),
        ];
    }

    private function clearCaches(): void
    {
        Cache::forget('gold:market-summary:data');
        Cache::forget('gold:market-summary');

        $ranges = config('gold.chart_available_ranges', []);
        foreach ($this->catalog->keys() as $itemKey) {
            foreach ($ranges as $rangeKey) {
                Cache::forget("gold:market-history:{$itemKey}:{$rangeKey}");
            }
        }
    }

    /** Drop points older than chart max window. At most once per day. */
    private function pruneOldPoints(): void
    {
        $days = (int)config('gold.history_retention_days', 400);
        if ($days < 1 || Cache::get('gold:last-history-prune')) {
            return;
        }

        // ponytail: plain DELETE by age; hourly rollup if table still grows too fast under sub-minute ingest
        PricePoint::where('fetched_at', '<', now()->subDays($days))->delete();
        Cache::put('gold:last-history-prune', 1, now()->addDay());
    }

    private function markFetchFailed(string $reference, string $source, \Throwable $e): void
    {
        $message = Str::limit($e->getMessage(), 500);
        $this->fetchStatus->fail($message);
        $this->clearCaches();

        Log::error('Gold price fetch failed', [
            'reference_id' => $reference,
            'source' => $source,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
        ]);

        report($e);
    }
}
