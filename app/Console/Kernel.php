<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\FetchEstjtPrices::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        $minutes = max(1, (int) config('gold.fetch_interval_minutes', 5));
        $schedule->command('gold:fetch-prices')->everyMinute()->when(fn () => now()->minute % $minutes === 0)->withoutOverlapping();
    }
}
