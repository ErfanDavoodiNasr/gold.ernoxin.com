<?php

namespace App\Console\Commands;

use App\Services\AutoPriceFetcher;
use Illuminate\Console\Command;

class FetchEstjtPrices extends Command
{
    protected $signature = 'gold:fetch-prices {--force : دریافت قیمت بدون بررسی فاصله زمانی}';
    protected $description = 'دریافت و ذخیره قیمت‌های طلا و سکه از estjt.ir';

    public function handle(AutoPriceFetcher $fetcher, \App\Services\DatabaseMigrator $migrator): int
    {
        $migrator->ensureMigrated();

        $result = $fetcher->fetchIfDue((bool)$this->option('force'));
        $status = $result['status'] ?? null;

        if ($status === 'skipped') {
            $reason = $result['reason'] ?? 'interval';
            if ($reason === 'locked') {
                $this->line('دریافت دیگری در حال اجراست؛ این اجرا رد شد.');
            } else {
                $this->line('زمان دریافت بعدی هنوز نرسیده است.');
            }

            return self::SUCCESS;
        }

        if ($status === 'failed') {
            $this->error('دریافت قیمت ناموفق بود: ' . ($result['error'] ?? 'خطای نامشخص'));

            return self::FAILURE;
        }

        $items = (int)($result['items'] ?? 0);
        $reference = (string)($result['referenceId'] ?? '');
        $this->info("{$items} مورد ذخیره شد. شناسه: {$reference}");

        return self::SUCCESS;
    }
}
