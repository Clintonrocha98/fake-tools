<?php

declare(strict_types=1);

use He4rt\FakeBinance\Fiat\Http\Controllers\CreateFiatDepositController;
use He4rt\FakeBinance\Fiat\Http\Controllers\GetFiatOrderDetailController;
use He4rt\FakeBinance\Fiat\Http\Controllers\RequestFiatWithdrawalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['fake-binance.request-log', 'fake-binance.scenario-switches', 'api', 'fake-binance.signed'])->post('/sapi/v1/fiat/deposit', CreateFiatDepositController::class);
Route::middleware(['fake-binance.request-log', 'fake-binance.scenario-switches', 'api', 'fake-binance.signed'])->get('/sapi/v1/fiat/get-order-detail', GetFiatOrderDetailController::class);
Route::middleware(['fake-binance.request-log', 'fake-binance.scenario-switches', 'api', 'fake-binance.signed'])->post('/sapi/v2/fiat/withdraw', RequestFiatWithdrawalController::class);
