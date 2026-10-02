<?php

namespace App\Console;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\FetchEstjtPrices::class,
        \App\Console\Commands\RepairPriceData::class,
        \App\Console\Commands\BackfillHourlyRollups::class,
    ];

    protected function schedule($schedule)
    {
        $schedule->command('gold:fetch-prices')
            ->everyFiveMinutes()
            ->withoutOverlapping(2);
    }
}
