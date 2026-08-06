<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Avanço automático (lazy)
    |--------------------------------------------------------------------------
    |
    | Segundos entre cada avanço de status (0 Pending → 6 Credited → 1 Success).
    | O avanço é LAZY: só acontece quando o hisrec é lido, nunca por um
    | scheduler — a idade do depósito (desde `announced_at`) contra este valor
    | decide se já é hora de avançar. O ledger é creditado UMA vez, ao entrar
    | em Credited (ADR-0003).
    |
    */

    'advance_seconds' => (int) env('FAKE_BINANCE_DEPOSIT_ADVANCE_SECONDS', 60),

    /*
    |--------------------------------------------------------------------------
    | Endereços de depósito por rede
    |--------------------------------------------------------------------------
    |
    | Endereço fixo por rede — o formato do endereço é da chain, não do coin,
    | então a chave é o código de rede da Binance (o mesmo vocabulário de
    | `fake-binance-withdraw.fees`). Rede fora deste mapa é recusada: nunca
    | inventamos um endereço para uma chain não mapeada.
    |
    */

    'addresses' => [
        'SOL' => env('FAKE_BINANCE_DEPOSIT_ADDRESS_SOL', 'FakeBinanceSolDepositAddress1111111111111111'),
        'ETH' => env('FAKE_BINANCE_DEPOSIT_ADDRESS_ETH', '0x00000000000000000000000000000000fak3b1na'),
        'TRX' => env('FAKE_BINANCE_DEPOSIT_ADDRESS_TRX', 'TFakeBinanceTrxDepositAddress0000000001'),
    ],

];
