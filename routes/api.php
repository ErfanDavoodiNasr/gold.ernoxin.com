<?php

use App\Http\Controllers\Api\MarketController;
use Illuminate\Support\Facades\Route;

Route::get('/market/summary', [MarketController::class, 'summary']);
Route::get('/market/items/{item}/history', [MarketController::class, 'history']);
