<?php

use App\Http\Controllers\PricePageController;
use Illuminate\Support\Facades\Route;

Route::get('/price/', PricePageController::class);
Route::get('/price/trends/{days}', PricePageController::class)->whereNumber('days');
Route::get('/{any?}', PricePageController::class)->where('any', '.*');
