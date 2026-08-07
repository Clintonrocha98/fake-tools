<?php

declare(strict_types=1);

use He4rt\FakeBinance\Ledger\Http\Controllers\AccountController;
use Illuminate\Support\Facades\Route;

Route::middleware(['fake-binance.request-log', 'fake-binance.scenario-switches', 'api', 'fake-binance.signed'])->get('/api/v3/account', AccountController::class);
