<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Beneficiário de referência (o fixture do consumidor)
    |--------------------------------------------------------------------------
    |
    | A chave que o seed registra verbatim a partir de `dict_key.json` do
    | consumidor. É contra ela que os testes resolvem e é ela que o payout
    | manual de dev usa.
    |
    */

    'reference' => [
        'pix_key' => env('FAKE_STARKBANK_DICT_REFERENCE_PIX_KEY', 'ada@brd.digital'),
        'type' => env('FAKE_STARKBANK_DICT_REFERENCE_TYPE', 'email'),
        'name' => env('FAKE_STARKBANK_DICT_REFERENCE_NAME', 'Ada Lovelace'),
        'tax_id' => env('FAKE_STARKBANK_DICT_REFERENCE_TAX_ID', '012.345.678-90'),
        'owner_type' => env('FAKE_STARKBANK_DICT_REFERENCE_OWNER_TYPE', 'naturalPerson'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Funding cross-fake
    |--------------------------------------------------------------------------
    |
    | A entry que o fake-binance embute no BR Code EMV estático do funding da
    | venue. Os dois fakes NUNCA se consultam em runtime: o contrato viaja por
    | estas constantes, replicadas dos dois lados (`FAKE_BINANCE_FIAT_PIX_KEY`
    | lá, `FAKE_STARKBANK_FUNDING_PIX_KEY` aqui) e conferidas pelo consumidor em
    | `treasury.conversion.funding_expected_tax_id`. Trocar o valor de um lado
    | só faz os dois guards do `SendConversionFunding` virarem teatro.
    |
    */

    'funding' => [
        'pix_key' => env('FAKE_STARKBANK_FUNDING_PIX_KEY', 'funding@fake-binance.dev'),
        'tax_id' => env('FAKE_STARKBANK_FUNDING_TAX_ID', '20.018.183/0001-80'),
        'name' => env('FAKE_STARKBANK_FUNDING_NAME', 'Fake Binance'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Instituição das entries semeadas
    |--------------------------------------------------------------------------
    |
    | O banco para onde toda chave do seed roteia. O ISPB é o mesmo do
    | `bankCode` que o consumidor manda de volta no POST /v2/transfer — oito
    | dígitos que ninguém confere de olho, por isso o nome viaja junto.
    |
    */

    'bank' => [
        'name' => env('FAKE_STARKBANK_DICT_BANK_NAME', 'Stark Bank S.A.'),
        'ispb' => env('FAKE_STARKBANK_DICT_ISPB', '20018183'),
        'account_type' => env('FAKE_STARKBANK_DICT_ACCOUNT_TYPE', 'checking'),
        'status' => env('FAKE_STARKBANK_DICT_STATUS', 'registered'),
    ],

];
