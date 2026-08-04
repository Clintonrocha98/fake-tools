<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Advance seconds
    |--------------------------------------------------------------------------
    |
    | Idade (segundos) que uma FiatOrder em ORDER_PROCESSING precisa acumular
    | para o avanço lazy virar ORDER_SUCCESS na próxima leitura de
    | GET /sapi/v1/fiat/get-order-detail — sem scheduler, calculado e
    | persistido no momento da leitura.
    |
    */

    'advance_seconds' => (int) env('FAKE_BINANCE_FIAT_ADVANCE_SECONDS', 60),

    /*
    |--------------------------------------------------------------------------
    | Status dialect
    |--------------------------------------------------------------------------
    |
    | Qual dialeto de status a wire fala: `live` (default, SCREAMING_SNAKE
    | observado ao vivo — ORDER_PROCESSING, ORDER_SUCCESS, …) ou `classic`
    | (dialeto documentado — Processing, Successful, …). Ver
    | He4rt\Venue\Fiat\Enums\FiatStatusDialect.
    |
    */

    'status_dialect' => env('FAKE_BINANCE_FIAT_STATUS_DIALECT', 'live'),

    /*
    |--------------------------------------------------------------------------
    | Deposit enabled
    |--------------------------------------------------------------------------
    |
    | Interruptor "sob comando" da recusa síncrona 100001 ("fiat service not
    | enabled") — quando false, todo POST /sapi/v1/fiat/deposit recusa com
    | HTTP 200 + code 100001, reproduzindo a fixture real do monolito.
    |
    */

    'deposit_enabled' => env('FAKE_BINANCE_FIAT_DEPOSIT_ENABLED', default: true),

    /*
    |--------------------------------------------------------------------------
    | Supported currency / payment method
    |--------------------------------------------------------------------------
    |
    | Único par aceito por este fake; um POST fora deste par recusa com
    | -16010 ("moeda ou método de pagamento não suportado").
    |
    */

    'supported_currency' => env('FAKE_BINANCE_FIAT_SUPPORTED_CURRENCY', 'BRL'),

    'supported_payment_method' => env('FAKE_BINANCE_FIAT_SUPPORTED_PAYMENT_METHOD', 'Pix'),

    /*
    |--------------------------------------------------------------------------
    | Deposit limit
    |--------------------------------------------------------------------------
    |
    | Teto opcional (decimal-string) de `amount` por depósito — `null` (default)
    | nunca recusa por limite. Um `amount` acima do teto recusa com -16007.
    |
    */

    'deposit_limit' => env('FAKE_BINANCE_FIAT_DEPOSIT_LIMIT'),

];
