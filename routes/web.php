<?php

use App\Http\Controllers\KeywordHubController;
use App\Http\Controllers\LearnPageController;
use App\Http\Controllers\PricePageController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', SitemapController::class);
Route::get('/blog/feed.xml', [LearnPageController::class, 'feed']);
Route::get('/blog', [LearnPageController::class, 'index']);
Route::get('/blog/search-index.json', [LearnPageController::class, 'searchIndexJson']);
Route::get('/blog/{slug}', [LearnPageController::class, 'show'])->where('slug', '[a-z0-9-]+');
Route::get('/', PricePageController::class);
Route::get('/price/mozaneh', fn() => app(KeywordHubController::class)('mozaneh'));
Route::get('/price/coin-bubble', fn() => app(KeywordHubController::class)('coin-bubble'));
Route::get('/price/ounce', fn() => app(KeywordHubController::class)('ounce'));
Route::get('/price/', PricePageController::class);
Route::get('/price/trends/{days}', PricePageController::class)->whereNumber('days');
