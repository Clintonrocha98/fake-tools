<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Invoice\Http\Controllers\GetInvoiceController;
use He4rt\FakeStarkbank\Invoice\Http\Controllers\IssueInvoiceController;
use He4rt\FakeStarkbank\Invoice\Http\Controllers\ListInvoicesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'fake-starkbank.signed'])->group(static function (): void {
    Route::post('/v2/invoice', IssueInvoiceController::class);
    Route::get('/v2/invoice', ListInvoicesController::class);
    Route::get('/v2/invoice/{id}', GetInvoiceController::class);
});
