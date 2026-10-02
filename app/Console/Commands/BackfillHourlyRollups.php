<?php

namespace App\Console\Commands;

use App\Models\PricePoint;
use App\Services\MarketCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillHourlyRollups extends Command
{
    protected $signature = 'gold:backfill-hourly
                            {--force : backfill حتی اگر قبلاً انجام شده باشد}';

    protected $description = 'پر کردن price_points_hourly از دادهٔ خام (یک‌بار بعد از deploy)';

    public function handle(MarketCatalog $catalog): int
    {
        if (!Schema::hasTable('price_points_hourly')) {
            $this->error('جدول price_points_hourly وجود ندارد. ابتدا database/schema/patches/2026-08-perf-raw-and-hourly.sql را اجرا کنید.');

            return self::FAILURE;
        }

        if (!PricePoint::query()->limit(1)->exists()) {
            $this->warn('price_points خالی است — چیزی برای backfill نیست.');

            return self::SUCCESS;
        }

        if (!$this->option('force') && Cache::get('gold:hourly-backfill-done')) {
            $this->line('backfill قبلاً انجام شده (gold:backfill-hourly --force برای تکرار).');

            return self::SUCCESS;
        }

        if (!$this->option('force') && DB::table('price_points_hourly')->limit(1)->exists()) {
            $this->line('price_points_hourly از قبل داده دارد — برای بازنویسی --force بزنید.');

            return self::SUCCESS;
        }

        $count = 0;
        foreach ($catalog->keys() as $itemKey) {
            DB::statement(
                'INSERT INTO price_points_hourly (item_key, bucket_at, current_value, created_at, updated_at)
                 SELECT item_key, bucket_at, current_value, NOW(), NOW()
                 FROM (
                     SELECT item_key,
                            FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(fetched_at) / 3600) * 3600) AS bucket_at,
                            current_value,
                            ROW_NUMBER() OVER (
                                PARTITION BY FLOOR(UNIX_TIMESTAMP(fetched_at) / 3600)
                                ORDER BY fetched_at DESC
                            ) AS rn
                     FROM price_points
                     WHERE item_key = ? AND current_value > 0
                 ) ranked
                 WHERE rn = 1
                 ON DUPLICATE KEY UPDATE
                    current_value = VALUES(current_value),
                    updated_at = VALUES(updated_at)',
                [$itemKey],
            );
            $count++;
        }

        Cache::put('gold:hourly-backfill-done', 1, now()->addYear());
        Cache::increment('gold:price-data-version');
        $this->info("backfill برای {$count} نماد انجام شد.");

        return self::SUCCESS;
    }
}
