<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | O par que o monolito consumidor configura como BINANCE_API_KEY/SECRET em
    | dev/testing — o fake valida assinaturas contra este mesmo par. As chaves
    | levam o prefixo FAKE_ (renomeação intencional, para não colidir com um
    | .env que também aponte para a Binance real) e devem espelhar o valor
    | configurado no monolito.
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

    'recv_window' => (int) env('FAKE_BINANCE_RECV_WINDOW', 5_000),

];
