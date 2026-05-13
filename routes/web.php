<?php

use App\Http\Controllers\LearnPageController;
use App\Http\Controllers\PricePageController;
use Illuminate\Support\Facades\Route;

Route::get('/learn', [LearnPageController::class, 'index']);
Route::get('/learn/{slug}', [LearnPageController::class, 'show'])->where('slug', '[a-z0-9-]+');
Route::get('/price/', PricePageController::class);
Route::get('/price/trends/{days}', PricePageController::class)->whereNumber('days');
Route::get('/{any?}', PricePageController::class)->where('any', '.*');
