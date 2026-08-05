<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Saldos iniciais do ledger
    |--------------------------------------------------------------------------
    |
    | Formato "ASSET:AMOUNT,ASSET:AMOUNT" — ex.: "BRL:100000,USDC:0". Parseado por
    | He4rt\Venue\Ledger\Support\SeedBalancesParser e aplicado por
    | He4rt\Venue\Database\Seeders\LedgerAccountSeeder.
    |
    */
    'seed_balances' => env('FAKE_BINANCE_SEED_BALANCES'),

];
