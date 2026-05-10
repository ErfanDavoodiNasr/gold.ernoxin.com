<?php

namespace App\Services;

use App\Models\FetchLog;
use App\Models\MarketItem;
use App\Models\PricePoint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PriceIngestor
{
    public function __construct(private EstjtScraper $scraper)
    {
    }

    public function fetchAndStore(): array
    {
        $reference = (string)Str::uuid();
        $log = FetchLog::create([
            'source' => config('gold.source_key', 'estjt'),
            'status' => 'running',
            'reference_id' => $reference,
            'started_at' => now(),
        ]);
        try {
            $payload = $this->scraper->fetch();
            $count = DB::transaction(fn() => $this->store($payload));
            $log->update(['status' => 'success', 'items_count' => $count, 'finished_at' => now()]);
            return ['referenceId' => $reference, 'items' => $count, 'payload' => $payload];
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'message' => $e->getMessage(), 'finished_at' => now()]);
            report($e);
            throw $e;
        }
    }

    public function store(array $payload): int
    {
        $count = 0;
        $source = $payload['source']['key'] ?? config('gold.source_key', 'estjt');
        $fetchedAt = isset($payload['source']['fetchedAt']) ? Carbon::parse($payload['source']['fetchedAt']) : now();
        foreach (['gold', 'coin'] as $group) {
            foreach (($payload[$group] ?? []) as $row) {
                $normalized = PersianNumber::label($row['type']);
                $item = MarketItem::updateOrCreate(
                    ['source' => $source, 'normalized_name' => $normalized],
                    [
                        'category' => $group,
                        'name' => $row['type'],
                        'currency' => $row['current']['currency'] ?? null,
                        'is_active' => true,
                        'meta' => ['source_url' => config('gold.source_url')],
                    ]
                );
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
}
