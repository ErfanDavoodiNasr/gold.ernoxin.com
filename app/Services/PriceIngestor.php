<?php

namespace App\Services;

use App\Models\PricePoint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
        $count = 0;
        $expected = count($this->catalog->keys());
        $committed = false;

        try {
            $this->fetchStatus->start($reference);

            $payload = $this->scraper->fetch();
            $count = DB::transaction(function () use ($payload, $expected) {
                $stored = $this->store($payload);
                // Fail closed: a half-empty snapshot is worse than keeping the last good one.
                $minimum = $expected > 0 ? max(1, (int)ceil($expected * 0.5)) : 1;
                if ($stored < $minimum) {
                    throw new \RuntimeException(
                        "تعداد نمادهای معتبر کافی نیست ({$stored}/{$expected}). داده مشکوک ذخیره نشد."
                    );
                }

                return $stored;
            });
            $committed = true;

            if ($expected > 0 && $count < $expected) {
                $this->fetchStatus->partial($count, "فقط {$count} از {$expected} نماد به‌روزرسانی شد.");
            } else {
                $this->fetchStatus->succeed($count);
            }

            Log::info('Gold price ingest stored', [
                'reference_id' => $reference,
                'source' => $source,
                'items' => $count,
            ]);

            $this->clearCaches();
            Cache::increment('gold:price-data-version');
            $this->pruneOldPoints();

            return ['referenceId' => $reference, 'items' => $count, 'payload' => $payload];
        } catch (\Throwable $e) {
            if ($committed && $count > 0) {
                $message = Str::limit($e->getMessage(), 500);
                $this->fetchStatus->partial($count, $message);
                Log::warning('Gold price ingest post-commit failure', [
                    'reference_id' => $reference,
                    'source' => $source,
                    'items' => $count,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]);
            } else {
                $this->markFetchFailed($reference, $source, $e);
            }

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

                $row = $this->normalizer->normalizeRow($row, $referenceToman, $isUsd);
                if ($row === null || !$this->validPrice($row['current']['value'] ?? null)) {
                    report(new \RuntimeException("Invalid zero or empty price skipped for {$normalized}"));
                    continue;
                }

                $current = (float)$row['current']['value'];
                if (!$isUsd && $referenceToman !== null && $referenceToman > 0
                    && ($this->normalizer->looksLikeRialSpike($current, $referenceToman)
                        || $this->normalizer->looksLikeTomanDip($current, $referenceToman))) {
                    report(new \RuntimeException("Unit spike rejected at ingest for {$normalized}"));
                    continue;
                }
                $pending[] = [
                    'item_key' => $normalized,
                    'current' => $current,
                    'source_high' => $row['high']['value'] ?? null,
                    'source_low' => $row['low']['value'] ?? null,
                    'yesterday_avg_value' => $row['yesterdayAvg']['value'],
                    'change_value' => $row['change']['value'],
                    'change_percent' => $row['change']['percent'],
                    'direction' => $row['change']['direction'],
                ];
                $referencePrices[$normalized] = $current;
            }
        }

        if ($pending === []) {
            return 0;
        }

        $needDayStats = array_values(array_filter(
            $pending,
            fn(array $item) => !$this->validPrice($item['source_high']) || !$this->validPrice($item['source_low']),
        ));
        $dayStats = $needDayStats === []
            ? []
            : $this->dailyRangesFor(array_column($needDayStats, 'item_key'), $fetchedAt);

        $now = now();
        $rows = [];
        $hourly = [];
        $bucketAt = $fetchedAt->copy()->startOfHour()->format('Y-m-d H:i:s');
        // Legacy installs may still have NOT NULL raw_payload until DROP patch runs.
        $legacyRaw = Schema::hasColumn('price_points', 'raw_payload');

        foreach ($pending as $item) {
            [$high, $low] = $this->resolveDailyRange(
                $item['current'],
                $item['source_high'],
                $item['source_low'],
                $dayStats[$item['item_key']] ?? null,
            );

            $row = [
                'item_key' => $item['item_key'],
                'fetched_at' => $fetchedAt,
                'current_value' => $item['current'],
                'high_value' => $high,
                'low_value' => $low,
                'yesterday_avg_value' => $item['yesterday_avg_value'],
                'change_value' => $item['change_value'],
                'change_percent' => $item['change_percent'],
                'direction' => $item['direction'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($legacyRaw) {
                $row['raw_payload'] = '{}';
            }
            $rows[] = $row;

            $hourly[] = [
                'item_key' => $item['item_key'],
                'bucket_at' => $bucketAt,
                'current_value' => $item['current'],
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
                'updated_at',
            ],
        );

        if (Schema::hasTable('price_points_hourly')) {
            DB::table('price_points_hourly')->upsert(
                $hourly,
                ['item_key', 'bucket_at'],
                ['current_value', 'updated_at'],
            );
        }

        return count($rows);
    }

    /** @return array<string, float> */
    private function latestReferencePrices(): array
    {
        $keys = $this->catalog->keys();
        if ($keys === []) {
            return [];
        }

        // Zeros rejected at ingest — keep MAX on UNIQUE(item_key, fetched_at) tip.
        $latest = PricePoint::query()
            ->selectRaw('item_key, MAX(fetched_at) as max_fetched_at')
            ->whereIn('item_key', $keys)
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
     * One batched day-range query for keys that lack source high/low.
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
        foreach (['gold:market-summary:data:v2', 'gold:market-summary:data', 'gold:market-summary'] as $key) {
            for ($attempt = 0; $attempt < 3; $attempt++) {
                Cache::forget($key);
                if (Cache::get($key) === null) {
                    break;
                }
                usleep(50_000);
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

        $cutoff = now()->subDays($days);

        // ponytail: chunked DELETE — one huge wipe locks shared MySQL too long
        do {
            $deleted = PricePoint::where('fetched_at', '<', $cutoff)->limit(5000)->delete();
        } while ($deleted > 0);

        if (Schema::hasTable('price_points_hourly')) {
            do {
                $deleted = DB::table('price_points_hourly')->where('bucket_at', '<', $cutoff)->limit(5000)->delete();
            } while ($deleted > 0);
        }

        // Shrink legacy JSON left on disk until DROP COLUMN patch is applied.
        if (Schema::hasColumn('price_points', 'raw_payload')) {
            do {
                $n = DB::update('UPDATE price_points SET raw_payload = NULL WHERE raw_payload IS NOT NULL LIMIT 2000');
            } while ($n > 0);
        }

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
