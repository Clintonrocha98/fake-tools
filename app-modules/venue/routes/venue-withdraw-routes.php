<?php

declare(strict_types=1);

use He4rt\Venue\Withdraw\Http\Controllers\ApplyWithdrawController;
use He4rt\Venue\Withdraw\Http\Controllers\WithdrawHistoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'venue.signed'])->group(function (): void {
    Route::post('/sapi/v1/capital/withdraw/apply', ApplyWithdrawController::class);
    Route::get('/sapi/v1/capital/withdraw/history', WithdrawHistoryController::class);
});
