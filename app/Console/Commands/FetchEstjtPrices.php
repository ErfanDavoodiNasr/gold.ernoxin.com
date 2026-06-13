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

        if (($result['status'] ?? null) === 'skipped') {
            $this->line('زمان دریافت بعدی هنوز نرسیده است.');
            return self::SUCCESS;
        }

        if (($result['status'] ?? null) === 'failed') {
            $this->error('دریافت قیمت ناموفق بود: ' . ($result['error'] ?? 'خطای نامشخص'));
            return self::FAILURE;
        }

        $this->info("{$result['items']} مورد ذخیره شد. شناسه: {$result['referenceId']}");
        return self::SUCCESS;
    }
}
