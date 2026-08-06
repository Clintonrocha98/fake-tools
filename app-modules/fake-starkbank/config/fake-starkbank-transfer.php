<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Avanço automático (lazy)
    |--------------------------------------------------------------------------
    |
    | Segundos entre dois patamares do ciclo de vida do cash-out: 1× leva
    | created → processing, 2× leva a success. O avanço é LAZY: só acontece
    | quando a transfer é LIDA (GET single, listagem ou a checagem de
    | idempotência de um POST repetido), nunca por scheduler — a idade desde a
    | criação contra este valor é o que decide. Zero ou negativo congela a
    | transfer em `created` até um cenário forçá-la.
    |
    | `failed` e `returned` nunca saem daqui: são desfechos de cenário, não do
    | relógio.
    |
    */

    'advance_seconds' => (int) env('FAKE_STARKBANK_TRANSFER_ADVANCE_SECONDS', 60),

];
