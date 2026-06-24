<?php

namespace App\Providers;

use App\Services\MarketCatalog;
use App\Support\MarketItem;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/';

    public function boot()
    {
        Route::bind('item', function ($value): MarketItem {
            $item = app(MarketCatalog::class)->find((int)$value);
            abort_unless($item, 404);

            return $item;
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        $this->routes(function () {
            Route::prefix('api')->middleware('api')->group(base_path('routes/api.php'));
            Route::middleware('web')->group(base_path('routes/web.php'));
        });
    }
}
