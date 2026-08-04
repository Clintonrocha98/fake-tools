<?php

declare(strict_types=1);

use He4rt\Venue\Ledger\Http\Controllers\AccountController;
use Illuminate\Support\Facades\Route;

// Sem middleware por decisão de ticket: a assinatura HMAC ('venue.signed') é acoplada
// pelo coordenador da onda, no merge, não aqui.
Route::get('/api/v3/account', AccountController::class);
