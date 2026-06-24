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
            $this->fetchStatus->succeed($count);
            $this->clearSummaryCache();
            Cache::increment('gold:price-data-version');

            return ['referenceId' => $reference, 'items' => $count, 'payload' => $payload];
        } catch (\Throwable $e) {
            $this->markFetchFailed($reference, $source, $e);
            throw $e;
        }
    }

    public function store(array $payload): int
    {
        $count = 0;
        $fetchedAt = isset($payload['source']['fetchedAt']) ? Carbon::parse($payload['source']['fetchedAt']) : now();
        $referencePrices = $this->latestReferencePrices();

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

                PricePoint::updateOrCreate(
                    ['item_key' => $normalized, 'fetched_at' => $fetchedAt],
                    [
                        'current_value' => $row['current']['value'],
                        'high_value' => $row['high']['value'],
                        'low_value' => $row['low']['value'],
                        'yesterday_avg_value' => $row['yesterdayAvg']['value'],
                        'change_value' => $row['change']['value'],
                        'change_percent' => $row['change']['percent'],
                        'direction' => $row['change']['direction'],
                        'raw_payload' => $row,
                    ]
                );
                $referencePrices[$normalized] = (float)$row['current']['value'];
                $count++;
            }
        }

        return $count;
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
            ->pluck('current_value', 'item_key')
            ->map(fn($value) => (float)$value)
            ->all();
    }

    private function validPrice($value): bool
    {
        return $value !== null && is_numeric($value) && (float)$value > 0;
    }

    private function clearSummaryCache(): void
    {
        Cache::forget('gold:market-summary:data');
        Cache::forget('gold:market-summary');
    }

    private function markFetchFailed(string $reference, string $source, \Throwable $e): void
    {
        $message = Str::limit($e->getMessage(), 500);
        $this->fetchStatus->fail($message);
        $this->clearSummaryCache();

        Log::error('Gold price fetch failed', [
            'reference_id' => $reference,
            'source' => $source,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
        ]);

        report($e);
    }
}
