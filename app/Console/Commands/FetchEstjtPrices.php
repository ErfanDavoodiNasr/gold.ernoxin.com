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
        $result = $fetcher->fetchIfDue((bool)$this->option('force'));
        if (!$result) {
            $this->line('زمان دریافت بعدی هنوز نرسیده است.');
            return self::SUCCESS;
        }

        $this->info("{$result['items']} مورد ذخیره شد. شناسه: {$result['referenceId']}");
        return self::SUCCESS;
    }
}
