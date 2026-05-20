<?php

use App\Http\Controllers\LearnPageController;
use App\Http\Controllers\PricePageController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', SitemapController::class);
Route::get('/blog', [LearnPageController::class, 'index']);
Route::get('/blog/search-index.json', [LearnPageController::class, 'searchIndexJson']);
Route::get('/blog/{slug}', [LearnPageController::class, 'show'])->where('slug', '[a-z0-9-]+');
Route::get('/price/', PricePageController::class);
Route::get('/price/trends/{days}', PricePageController::class)->whereNumber('days');
Route::get('/{any?}', PricePageController::class)->where('any', '.*');
