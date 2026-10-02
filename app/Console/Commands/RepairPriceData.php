<?php

namespace App\Console\Commands;

use App\Models\PricePoint;
use App\Services\MarketCatalog;
use App\Services\PriceNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RepairPriceData extends Command
{
    protected $signature = 'gold:repair-price-data
                            {--dry-run : فقط گزارش بده، چیزی ذخیره نکن}
                            {--skip-units : فقط high/low را پر کن، اسپایک واحد را اصلاح نکن}';

    protected $description = 'اصلاح اسپایک‌های ریال/تومان در تاریخچه و پر کردن high/low خالی';

    public function handle(MarketCatalog $catalog, PriceNormalizer $normalizer): int
    {
        $dryRun = (bool)$this->option('dry-run');
        $skipUnits = (bool)$this->option('skip-units');

        if ($dryRun) {
            $this->warn('حالت dry-run — هیچ تغییری ذخیره نمی‌شود.');
        }

        $unitFixes = $skipUnits ? 0 : $this->repairUnitSpikes($catalog, $normalizer, $dryRun);
        $highLowFixes = $this->backfillDailyHighLow($dryRun);
        $hourlyFixes = $skipUnits ? 0 : $this->repairHourlySpikes($catalog, $normalizer, $dryRun);

        if (!$dryRun && ($unitFixes > 0 || $highLowFixes > 0 || $hourlyFixes > 0)) {
            Cache::increment('gold:price-data-version');
            Cache::forget('gold:market-summary:data:v2');
            Cache::forget('gold:market-summary:data');
            Cache::forget('gold:market-summary');
        }

        $this->info("اصلاح واحد: {$unitFixes} | high/low: {$highLowFixes} | hourly: {$hourlyFixes}");

        return self::SUCCESS;
    }

    private function repairUnitSpikes(MarketCatalog $catalog, PriceNormalizer $normalizer, bool $dryRun): int
    {
        $fixes = 0;

        foreach ($catalog->keys() as $itemKey) {
            $previous = null;
            $points = PricePoint::query()
                ->where('item_key', $itemKey)
                ->where('current_value', '>', 0)
                ->orderBy('fetched_at')
                ->get(['id', 'current_value', 'high_value', 'low_value']);

            foreach ($points as $point) {
                $current = (float)$point->current_value;
                $reference = $previous ?? $current;
                $repaired = $normalizer->repairAgainstReference($current, $reference);

                if (abs($repaired - $current) < 0.0001) {
                    $previous = $current;
                    continue;
                }

                $fixes++;
                $this->line("  {$itemKey} #{$point->id}: {$current} → {$repaired}");

                if (!$dryRun) {
                    $point->current_value = $repaired;
                    if ($this->validPrice($point->high_value) && $normalizer->looksLikeRialSpike((float)$point->high_value, $reference)) {
                        $point->high_value = round((float)$point->high_value / 10, 4);
                    }
                    if ($this->validPrice($point->low_value) && $normalizer->looksLikeRialSpike((float)$point->low_value, $reference)) {
                        $point->low_value = round((float)$point->low_value / 10, 4);
                    }
                    $point->save();
                }

                $previous = $repaired;
            }
        }

        return $fixes;
    }

    private function validPrice(mixed $value): bool
    {
        return $value !== null && is_numeric($value) && (float)$value > 0;
    }

    private function backfillDailyHighLow(bool $dryRun): int
    {
        $fixes = 0;

        $groups = PricePoint::query()
            ->selectRaw('item_key, DATE(fetched_at) as day, MAX(current_value) as day_high, MIN(current_value) as day_low')
            ->where('current_value', '>', 0)
            ->groupBy('item_key', DB::raw('DATE(fetched_at)'))
            ->get();

        foreach ($groups as $group) {
            $query = PricePoint::query()
                ->where('item_key', $group->item_key)
                ->whereDate('fetched_at', $group->day)
                ->where(function ($builder) {
                    $builder->whereNull('high_value')
                        ->orWhereNull('low_value')
                        ->orWhere('high_value', '<=', 0)
                        ->orWhere('low_value', '<=', 0);
                });

            $count = (clone $query)->count();
            if ($count === 0) {
                continue;
            }

            $fixes += $count;
            $this->line("  {$group->item_key} {$group->day}: {$count} ردیف high/low");

            if (!$dryRun) {
                $query->update([
                    'high_value' => (float)$group->day_high,
                    'low_value' => (float)$group->day_low,
                ]);
            }
        }

        return $fixes;
    }

    private function repairHourlySpikes(MarketCatalog $catalog, PriceNormalizer $normalizer, bool $dryRun): int
    {
        if (!Schema::hasTable('price_points_hourly')) {
            return 0;
        }

        $fixes = 0;

        foreach ($catalog->keys() as $itemKey) {
            $previous = null;
            $rows = DB::table('price_points_hourly')
                ->where('item_key', $itemKey)
                ->where('current_value', '>', 0)
                ->orderBy('bucket_at')
                ->get(['id', 'current_value']);

            foreach ($rows as $row) {
                $current = (float)$row->current_value;
                $reference = $previous ?? $current;
                $repaired = $normalizer->repairAgainstReference($current, $reference);

                if (abs($repaired - $current) < 0.0001) {
                    $previous = $current;
                    continue;
                }

                $fixes++;
                if (!$dryRun) {
                    DB::table('price_points_hourly')->where('id', $row->id)->update([
                        'current_value' => $repaired,
                        'updated_at' => now(),
                    ]);
                }

                $previous = $repaired;
            }
        }

        return $fixes;
    }
}
