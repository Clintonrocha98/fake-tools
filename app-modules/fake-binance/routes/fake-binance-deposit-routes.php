<?php

declare(strict_types=1);

use He4rt\FakeBinance\Deposit\Http\Controllers\DepositAddressController;
use He4rt\FakeBinance\Deposit\Http\Controllers\DepositHistoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['fake-binance.scenario-switches', 'api', 'fake-binance.signed'])->group(function (): void {
    Route::get('/sapi/v1/capital/deposit/address', DepositAddressController::class);
    Route::get('/sapi/v1/capital/deposit/hisrec', DepositHistoryController::class);
});
