<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | O par que o monolito consumidor configura como BINANCE_API_KEY/SECRET em
    | dev/testing — o fake valida assinaturas contra este mesmo par.
    |
    */

    'api_key' => env('FAKE_BINANCE_API_KEY', ''),

    'api_secret' => env('FAKE_BINANCE_API_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Recv Window
    |--------------------------------------------------------------------------
    |
    | Janela default (ms) aceita entre o timestamp do request e o horário do
    | servidor quando o request não informa `recvWindow` — espelha o default
    | do BinanceConnector do monolito.
    |
    */

    'recv_window' => 5_000,

];
