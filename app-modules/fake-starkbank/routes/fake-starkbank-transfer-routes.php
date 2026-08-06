<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Transfer\Http\Controllers\GetTransferController;
use He4rt\FakeStarkbank\Transfer\Http\Controllers\ListTransfersController;
use He4rt\FakeStarkbank\Transfer\Http\Controllers\SendTransferController;
use Illuminate\Support\Facades\Route;

Route::middleware(['fake-starkbank.scenario-switches', 'api', 'fake-starkbank.signed'])->group(static function (): void {
    Route::post('/v2/transfer', SendTransferController::class);
    Route::get('/v2/transfer', ListTransfersController::class);
    Route::get('/v2/transfer/{id}', GetTransferController::class);
});
