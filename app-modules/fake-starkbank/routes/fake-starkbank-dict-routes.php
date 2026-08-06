<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Dict\Http\Controllers\ResolveDictKeyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'fake-starkbank.signed'])->group(static function (): void {
    // A chave PIX viaja no path e pode conter `@`, `+` e `.` — só a barra fica
    // de fora, que é justamente o default do parâmetro de rota.
    Route::get('/v2/dict-key/{key}', ResolveDictKeyController::class);
});
