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
    | He4rt\FakeBinance\Fiat\Enums\FiatStatusDialect.
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
    | Withdraw payment method
    |--------------------------------------------------------------------------
    |
    | O método aceito por POST /sapi/v2/fiat/withdraw — a doc só documenta
    | `bank_transfer` para a saída (o `Pix` acima é o dialeto da ENTRADA, que o
    | consumidor manda no /sapi/v1/fiat/deposit). Fora deste método (ou da
    | supported_currency), recusa com -16010 em HTTP 200.
    |
    */

    'withdraw_payment_method' => env('FAKE_BINANCE_FIAT_WITHDRAW_PAYMENT_METHOD', 'bank_transfer'),

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

    /*
    |--------------------------------------------------------------------------
    | BR Code EMV estático do depósito
    |--------------------------------------------------------------------------
    |
    | O que o `pixcode` de GET /sapi/v1/fiat/get-order-detail carrega: chave PIX
    | (campo 26, sub 01) e nome/cidade do merchant (campos 59/60) do BR Code que
    | He4rt\FakeBinance\Fiat\Actions\BuildStaticBrcode monta. O valor (campo 54)
    | nunca é config — é sempre o `amount` da FiatOrder.
    |
    | `pix_key` é metade de um contrato entre dois serviços que nunca se
    | consultam em runtime: o fake-starkbank resolve exatamente esta chave no seu
    | registro DICT (`FAKE_STARKBANK_FUNDING_PIX_KEY`, mesmo default) e devolve
    | dela o taxId que o consumidor confere contra
    | `treasury.conversion.funding_expected_tax_id`. Mudar um lado só e os dois
    | guards do SendConversionFunding viram teatro.
    |
    */

    'pix_key' => env('FAKE_BINANCE_FIAT_PIX_KEY', 'funding@fake-binance.dev'),

    'merchant_name' => env('FAKE_BINANCE_FIAT_MERCHANT_NAME', 'Fake Binance'),

    'merchant_city' => env('FAKE_BINANCE_FIAT_MERCHANT_CITY', 'Sao Paulo'),

];
