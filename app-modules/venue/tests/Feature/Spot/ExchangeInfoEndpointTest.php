<?php

declare(strict_types=1);

it('returns symbols[] with the base/quote precisions and the LOT_SIZE/NOTIONAL filters, public and unsigned', function (): void {
    $response = $this->getJson('/api/v3/exchangeInfo?symbol=USDCBRL');

    $response->assertOk()->assertJson([
        'symbols' => [[
            'symbol' => 'USDCBRL',
            'baseAsset' => 'USDC',
            'quoteAsset' => 'BRL',
            'baseAssetPrecision' => 8,
            'quoteAssetPrecision' => 8,
            'filters' => [
                ['filterType' => 'LOT_SIZE', 'minQty' => '0.00000001', 'maxQty' => '9000000.00000000', 'stepSize' => '0.00000001'],
                ['filterType' => 'NOTIONAL', 'minNotional' => '10', 'applyToMarket' => true],
            ],
        ]],
    ]);
});
