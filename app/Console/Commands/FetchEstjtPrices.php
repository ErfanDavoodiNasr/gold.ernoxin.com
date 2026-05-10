<?php

namespace App\Console\Commands;

use App\Services\AutoPriceFetcher;
use Illuminate\Console\Command;

class FetchEstjtPrices extends Command
{
    protected $signature = 'gold:fetch-prices {--force : دریافت قیمت بدون بررسی فاصله زمانی}';
    protected $description = 'دریافت و ذخیره قیمت‌های طلا و سکه از estjt.ir';

    public function handle(AutoPriceFetcher $fetcher): int
    {
        if (!config('gold.features.auto_fetch')) {
            $this->warn('دریافت خودکار غیرفعال است.');
            return self::SUCCESS;
        }

        $result = $fetcher->fetchIfDue((bool)$this->option('force'));
        if (!$result) {
            $this->line('زمان دریافت بعدی هنوز نرسیده یا دریافت دیگری در حال اجراست.');
            return self::SUCCESS;
        }

        $this->info("{$result['items']} مورد ذخیره شد. شناسه: {$result['referenceId']}");
        return self::SUCCESS;
    }
}
