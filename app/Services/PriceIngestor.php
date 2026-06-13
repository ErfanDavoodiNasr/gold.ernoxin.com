<?php

namespace App\Services;

use App\Models\FetchLog;
use App\Models\MarketItem;
use App\Models\PricePoint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PriceIngestor
{
    public function __construct(
        private EstjtScraper    $scraper,
        private PriceNormalizer $normalizer,
    )
    {
    }

    public function fetchAndStore(): array
    {
        $reference = (string)Str::uuid();
        $source = config('gold.source_key', 'estjt');
        $log = null;

        try {
            $log = FetchLog::create([
                'source' => $source,
                'status' => 'running',
                'reference_id' => $reference,
                'started_at' => now(),
            ]);

            $payload = $this->scraper->fetch();
            $count = DB::transaction(fn() => $this->store($payload));
            $log->update(['status' => 'success', 'items_count' => $count, 'finished_at' => now()]);
            $this->clearSummaryCache();
            Cache::increment('gold:price-data-version');

            return ['referenceId' => $reference, 'items' => $count, 'payload' => $payload];
        } catch (\Throwable $e) {
            $this->markFetchFailed($log, $reference, $source, $e);
            throw $e;
        }
    }

    public function store(array $payload): int
    {
        $count = 0;
        $source = $payload['source']['key'] ?? config('gold.source_key', 'estjt');
        $fetchedAt = isset($payload['source']['fetchedAt']) ? Carbon::parse($payload['source']['fetchedAt']) : now();
        $existingItems = MarketItem::query()
            ->where('source', $source)
            ->get()
            ->keyBy('normalized_name');

        foreach (['gold', 'coin'] as $group) {
            foreach (($payload[$group] ?? []) as $row) {
                $normalized = PersianNumber::label($row['type']);
                $existingItem = $existingItems->get($normalized);
                $referenceToman = $this->referencePrice($existingItem);
                $isUsd = $this->normalizer->isUsdItem($row['current']['currency'] ?? $existingItem?->currency, $group);

                $row = $this->normalizer->normalizeRow($row, $referenceToman, $isUsd);
                if ($row === null || !$this->validPrice($row['current']['value'] ?? null)) {
                    report(new \RuntimeException("Invalid zero or empty price skipped for {$normalized}"));
                    continue;
                }

                $item = MarketItem::updateOrCreate(
                    ['source' => $source, 'normalized_name' => $normalized],
                    [
                        'category' => $group,
                        'name' => $row['type'],
                        'currency' => $isUsd ? '$' : ($row['current']['currency'] ?? $existingItem?->currency),
                        'is_active' => true,
                        'meta' => ['source_url' => config('gold.source_url')],
                    ]
                );
                $existingItems->put($normalized, $item);

                PricePoint::updateOrCreate(
                    ['market_item_id' => $item->id, 'fetched_at' => $fetchedAt],
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
                $count++;
            }
        }
        return $count;
    }

    private function referencePrice(?MarketItem $item): ?float
    {
        if (!$item) {
            return null;
        }

        $latest = $item->prices()
            ->whereNotNull('current_value')
            ->where('current_value', '>', 0)
            ->latest('fetched_at')
            ->value('current_value');

        return $latest !== null ? (float)$latest : null;
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

    private function markFetchFailed(?FetchLog $log, string $reference, string $source, \Throwable $e): void
    {
        $message = Str::limit($e->getMessage(), 500);

        if ($log) {
            $log->update([
                'status' => 'failed',
                'message' => $message,
                'finished_at' => now(),
            ]);
        }

        $this->clearSummaryCache();

        Log::error('Gold price fetch failed', [
            'reference_id' => $reference,
            'source' => $source,
            'fetch_log_id' => $log?->id,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
        ]);

        report($e);
    }
}
