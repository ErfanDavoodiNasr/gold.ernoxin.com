<?php

namespace App\Console;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\FetchEstjtPrices::class,
    ];

    protected function schedule($schedule)
    {
        $schedule->command('gold:fetch-prices')
            ->everyMinute()
            ->withoutOverlapping(2);
    }
}
