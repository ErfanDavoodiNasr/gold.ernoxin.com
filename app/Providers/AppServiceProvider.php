<?php

namespace App\Providers;

use App\Services\SharedHostingBootstrap;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
    }

    public function boot()
    {
        app(SharedHostingBootstrap::class)->run();
    }
}
