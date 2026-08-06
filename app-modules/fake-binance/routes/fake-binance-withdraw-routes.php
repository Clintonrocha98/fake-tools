<?php

declare(strict_types=1);

use He4rt\FakeBinance\Withdraw\Http\Controllers\ApplyWithdrawController;
use He4rt\FakeBinance\Withdraw\Http\Controllers\CoinsInfoController;
use He4rt\FakeBinance\Withdraw\Http\Controllers\QuestionnaireRequirementsController;
use He4rt\FakeBinance\Withdraw\Http\Controllers\WithdrawHistoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['fake-binance.scenario-switches', 'api', 'fake-binance.signed'])->group(function (): void {
    Route::post('/sapi/v1/capital/withdraw/apply', ApplyWithdrawController::class);
    Route::get('/sapi/v1/capital/withdraw/history', WithdrawHistoryController::class);
    Route::get('/sapi/v1/localentity/questionnaire-requirements', QuestionnaireRequirementsController::class);
    Route::get('/sapi/v1/capital/config/getall', CoinsInfoController::class);
});
