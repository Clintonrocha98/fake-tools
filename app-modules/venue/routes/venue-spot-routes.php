<?php

declare(strict_types=1);

use He4rt\Venue\Spot\Http\Controllers\BookTickerController;
use He4rt\Venue\Spot\Http\Controllers\ExchangeInfoController;
use He4rt\Venue\Spot\Http\Controllers\GetOrderController;
use He4rt\Venue\Spot\Http\Controllers\PlaceOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api'])->group(function (): void {
    Route::get('/api/v3/ticker/bookTicker', BookTickerController::class);
    Route::get('/api/v3/exchangeInfo', ExchangeInfoController::class);
});

Route::middleware(['api', 'venue.signed'])->group(function (): void {
    Route::post('/api/v3/order', PlaceOrderController::class);
    Route::get('/api/v3/order', GetOrderController::class);
});
