<?php

declare(strict_types=1);

use He4rt\Venue\Fiat\Http\Controllers\CreateFiatDepositController;
use He4rt\Venue\Fiat\Http\Controllers\GetFiatOrderDetailController;
use Illuminate\Support\Facades\Route;

Route::middleware(['venue.scenario-switches', 'api', 'venue.signed'])->post('/sapi/v1/fiat/deposit', CreateFiatDepositController::class);
Route::middleware(['venue.scenario-switches', 'api', 'venue.signed'])->get('/sapi/v1/fiat/get-order-detail', GetFiatOrderDetailController::class);
