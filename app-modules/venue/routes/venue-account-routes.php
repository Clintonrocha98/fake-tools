<?php

declare(strict_types=1);

use He4rt\Venue\Ledger\Http\Controllers\AccountController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'venue.signed'])->get('/api/v3/account', AccountController::class);
