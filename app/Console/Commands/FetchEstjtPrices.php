<?php

namespace App\Console\Commands;

use App\Services\PriceIngestor;
use Illuminate\Console\Command;

class FetchEstjtPrices extends Command
{
    protected $signature = 'gold:fetch-prices';
    protected $description = 'دریافت و ذخیره قیمت‌های طلا و سکه از estjt.ir';

    public function handle(PriceIngestor $ingestor): int
    {
        if (!config('gold.features.auto_fetch')) {
            $this->warn('دریافت خودکار غیرفعال است.');
            return self::SUCCESS;
        }
        $result = $ingestor->fetchAndStore();
        $this->info("{$result['items']} مورد ذخیره شد. شناسه: {$result['referenceId']}");
        return self::SUCCESS;
    }
}
