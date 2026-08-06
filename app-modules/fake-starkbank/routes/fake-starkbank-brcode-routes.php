<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Brcode\Http\Controllers\GetBrcodePaymentController;
use He4rt\FakeStarkbank\Brcode\Http\Controllers\ListBrcodePaymentsController;
use He4rt\FakeStarkbank\Brcode\Http\Controllers\PayBrcodeController;
use He4rt\FakeStarkbank\Brcode\Http\Controllers\PreviewBrcodeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['fake-starkbank.scenario-switches', 'api', 'fake-starkbank.signed'])->group(static function (): void {
    // O brcode viaja URL-encoded na query e entra na mensagem assinada
    // exatamente como o consumidor o montou.
    Route::get('/v2/brcode-preview', PreviewBrcodeController::class);

    Route::post('/v2/brcode-payment', PayBrcodeController::class);
    Route::get('/v2/brcode-payment', ListBrcodePaymentsController::class);
    Route::get('/v2/brcode-payment/{id}', GetBrcodePaymentController::class);
});
