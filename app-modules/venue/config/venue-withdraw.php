<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Avanço automático (lazy)
    |--------------------------------------------------------------------------
    |
    | Segundos entre cada avanço de status (2 Awaiting Approval → 4 Processing →
    | 6 Completed). O avanço é LAZY: só acontece quando o histórico é lido, nunca
    | por um scheduler — a idade do withdraw (desde `applied_at`) contra este
    | valor decide se já é hora de avançar.
    |
    */

    'advance_seconds' => env('FAKE_BINANCE_WITHDRAW_ADVANCE_SECONDS', 60),

    /*
    |--------------------------------------------------------------------------
    | Taxas por rede
    |--------------------------------------------------------------------------
    |
    | Taxa fixa (decimal string) cobrada por rede de destino — chave é o código
    | de rede da Binance (o que chega em `network` no apply), não o id canônico
    | do monolito. Rede sem taxa configurada cai no `default_fee`.
    |
    */

    'fees' => [
        'SOL' => '0.004',
        'ETH' => '0.003',
        'TRX' => '1',
    ],

    'default_fee' => '0',

];
