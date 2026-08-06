<?php

declare(strict_types=1);

use He4rt\FakeBinance\Spot\Http\Controllers\BookTickerController;
use He4rt\FakeBinance\Spot\Http\Controllers\ExchangeInfoController;
use He4rt\FakeBinance\Spot\Http\Controllers\GetOrderController;
use He4rt\FakeBinance\Spot\Http\Controllers\MyTradesController;
use He4rt\FakeBinance\Spot\Http\Controllers\OrderBookDepthController;
use He4rt\FakeBinance\Spot\Http\Controllers\PlaceOrderController;
use He4rt\FakeBinance\Spot\Http\Controllers\TickerPriceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['fake-binance.scenario-switches', 'api'])->group(function (): void {
    Route::get('/api/v3/ticker/bookTicker', BookTickerController::class);
    Route::get('/api/v3/ticker/price', TickerPriceController::class);
    Route::get('/api/v3/depth', OrderBookDepthController::class);
    Route::get('/api/v3/exchangeInfo', ExchangeInfoController::class);
});

Route::middleware(['fake-binance.scenario-switches', 'api', 'fake-binance.signed'])->group(function (): void {
    Route::post('/api/v3/order', PlaceOrderController::class);
    Route::get('/api/v3/order', GetOrderController::class);
    Route::get('/api/v3/myTrades', MyTradesController::class);
});
