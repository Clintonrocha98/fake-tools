<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Símbolos servidos
    |--------------------------------------------------------------------------
    |
    | Fonte do preço no fake, indexada pelo symbol da wire: preço fixo por env,
    | com spread bid/ask pequeno em torno do mid (ADR-0002, agora por símbolo).
    | `price` é o mid; bid = price - spread/2, ask = price + spread/2. Uma
    | ordem BUY preenche no ask (o comprador paga o ask), uma SELL preenche no
    | bid (o vendedor recebe o bid) — mesma convenção de qualquer order book.
    |
    | A fronteira de resolução do symbol é o enum SpotSymbol: um símbolo fora
    | dele responde -1121 e nunca cai numa entrada default deste mapa.
    |
    */

    'symbols' => [

        'USDCBRL' => [
            'price' => env('FAKE_BINANCE_USDCBRL_PRICE', '5.10'),
            'spread' => env('FAKE_BINANCE_USDCBRL_SPREAD', '0.02'),
            'base_asset' => 'USDC',
            'quote_asset' => 'BRL',
            'base_asset_precision' => 8,
            'quote_asset_precision' => 8,
            'bid_qty' => '10',
            'ask_qty' => '10',

            'filters' => [
                'step_size' => '0.00000001',
                'min_qty' => '0.00000001',
                'max_qty' => '9000000.00000000',
                'min_notional' => '10',
            ],
        ],

        'USDTBRL' => [
            'price' => env('FAKE_BINANCE_USDTBRL_PRICE', '5.10'),
            'spread' => env('FAKE_BINANCE_USDTBRL_SPREAD', '0.02'),
            'base_asset' => 'USDT',
            'quote_asset' => 'BRL',
            'base_asset_precision' => 8,
            'quote_asset_precision' => 8,
            'bid_qty' => '10',
            'ask_qty' => '10',

            'filters' => [
                'step_size' => '0.00000001',
                'min_qty' => '0.00000001',
                'max_qty' => '9000000.00000000',
                'min_notional' => '10',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Comissão
    |--------------------------------------------------------------------------
    |
    | Taxa aplicada sobre o ativo recebido em cada execução MARKET — espelha o
    | taker fee padrão da Binance (0.1%).
    |
    */

    'commission_rate' => env('FAKE_BINANCE_SPOT_COMMISSION_RATE', '0.001'),

];
