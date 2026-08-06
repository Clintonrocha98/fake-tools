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

    /*
    |--------------------------------------------------------------------------
    | Travel Rule
    |--------------------------------------------------------------------------
    |
    | País cujo questionário o gate de travel rule exige antes de um saque em
    | carteira. Vazio/ausente = sem exigência ({"questionnaireCountryCode": null},
    | o happy path do SendWalletWithdraw do consumidor). O valor é servido
    | verbatim — `NIL` também é lido como "sem exigência" pelo consumidor.
    |
    */

    'travel_rule_questionnaire_country' => env('FAKE_BINANCE_TRAVEL_RULE_COUNTRY'),

];
